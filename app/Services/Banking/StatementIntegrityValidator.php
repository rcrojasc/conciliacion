<?php

namespace App\Services\Banking;

class StatementIntegrityValidator
{
    private const DEFAULT_TOLERANCE = 0.01;

    /**
     * Valida matemáticamente una secuencia de movimientos bancarios.
     *
     * Cada elemento debe contener:
     *
     * - amount
     * - direction
     * - balance_after_reported
     *
     * La secuencia debe respetar el orden original de la cartola.
     */
    public function validate(
        array $rows,
        float $tolerance = self::DEFAULT_TOLERANCE
    ): array {
        if (empty($rows)) {
            return [
                'valid' => true,
                'checked_transitions' => 0,
                'inconsistencies' => [],
            ];
        }

        $inconsistencies = [];
        $checkedTransitions = 0;

        /*
        |--------------------------------------------------------------------------
        | Comenzamos desde el saldo posterior del primer movimiento
        |--------------------------------------------------------------------------
        |
        | No necesitamos reconstruir el saldo anterior al primer movimiento
        | para validar la continuidad interna de la cartola.
        |
        | A partir del segundo movimiento comprobamos:
        |
        | saldo anterior
        | + abono / - cargo
        | =
        | saldo posterior reportado
        |
        */

        $previousBalance =
            $this->nullableFloat(
                $rows[0]['balance_after_reported'] ?? null
            );

        for (
            $index = 1;
            $index < count($rows);
            $index++
        ) {
            $row = $rows[$index];

            $currentBalance =
                $this->nullableFloat(
                    $row['balance_after_reported'] ?? null
                );

            /*
             * Si alguno de los saldos no está disponible,
             * no inventamos información.
             */
            if (
                $previousBalance === null
                || $currentBalance === null
            ) {
                $previousBalance =
                    $currentBalance;

                continue;
            }

            $amount =
                abs(
                    (float) ($row['amount'] ?? 0)
                );

            $direction =
                $this->directionValue(
                    $row['direction'] ?? null
                );

            $expectedBalance =
                match ($direction) {
                    'credit' =>
                        $previousBalance + $amount,

                    'debit' =>
                        $previousBalance - $amount,

                    default =>
                        null,
                };

            if ($expectedBalance === null) {
                $inconsistencies[] = [
                    'position' =>
                        $row['position'] ?? ($index + 1),

                    'type' =>
                        'unsupported_direction',

                    'direction' =>
                        $direction,
                ];

                $previousBalance =
                    $currentBalance;

                continue;
            }

            $checkedTransitions++;

            $difference =
                $currentBalance - $expectedBalance;

            if (
                abs($difference) > $tolerance
            ) {
                $inconsistencies[] = [
                    'position' =>
                        $row['position'] ?? ($index + 1),

                    'type' =>
                        'balance_mismatch',

                    'previous_balance' =>
                        $previousBalance,

                    'amount' =>
                        $amount,

                    'direction' =>
                        $direction,

                    'expected_balance' =>
                        $expectedBalance,

                    'reported_balance' =>
                        $currentBalance,

                    'difference' =>
                        $difference,
                ];
            }

            /*
             * Para la siguiente comprobación utilizamos siempre el
             * saldo REAL reportado por el banco.
             *
             * Así una inconsistencia no genera artificialmente una
             * cascada de errores posteriores.
             */
            $previousBalance =
                $currentBalance;
        }

        return [
            'valid' =>
                empty($inconsistencies),

            'checked_transitions' =>
                $checkedTransitions,

            'inconsistencies' =>
                $inconsistencies,
        ];
    }

    private function nullableFloat(
        mixed $value
    ): ?float {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return (float) $value;
    }

    private function directionValue(
        mixed $direction
    ): string {
        if ($direction instanceof \BackedEnum) {
            return (string) $direction->value;
        }

        return strtolower(
            trim(
                (string) $direction
            )
        );
    }
}
