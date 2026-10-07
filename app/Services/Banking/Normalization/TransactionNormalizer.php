<?php

namespace App\Services\Banking\Normalization;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class TransactionNormalizer
{
    public function normalize(array $row, array $map): array
    {
        $get = function (string $key) use ($row, $map): mixed {
            $mappedColumn = $map[$key] ?? null;

            if ($mappedColumn === null || $mappedColumn === '') {
                return null;
            }

            // Coincidencia exacta.
            if (array_key_exists($mappedColumn, $row)) {
                return $row[$mappedColumn];
            }

            // Coincidencia normalizada.
            $wanted = $this->normalizeColumnName(
                (string) $mappedColumn
            );

            foreach ($row as $column => $value) {
                if (
                    $this->normalizeColumnName((string) $column)
                    === $wanted
                ) {
                    return $value;
                }
            }

            return null;
        };

        $bookingDateRaw = $get('booking_date');

        if (
            $bookingDateRaw === null
            || trim((string) $bookingDateRaw) === ''
        ) {
            throw new RuntimeException(
                'La transacción no contiene fecha.'
            );
        }

        $statementYear = isset($map['_statement_year'])
            ? (int) $map['_statement_year']
            : (int) now()->format('Y');

        $bookingDate = $this->date(
            $bookingDateRaw,
            $statementYear
        );

        $valueDate = null;

        $valueDateRaw = $get('value_date');

        if (
            $valueDateRaw !== null
            && trim((string) $valueDateRaw) !== ''
        ) {
            $valueDate = $this->date(
                $valueDateRaw,
                $statementYear
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Determinación del monto y dirección
        |--------------------------------------------------------------------------
        |
        | Algunos bancos entregan una única columna "Monto".
        | BancoEstado entrega columnas separadas:
        |
        | Abonos = credit
        | Cargos = debit
        |
        */

        $creditRaw = $get('credit_amount');
        $debitRaw = $get('debit_amount');

        $hasCredit = $this->hasValue($creditRaw);
        $hasDebit = $this->hasValue($debitRaw);

        if ($hasCredit && $hasDebit) {
            throw new RuntimeException(
                'La transacción contiene simultáneamente abono y cargo.'
            );
        }

        if ($hasCredit) {
            $amount = abs(
                $this->money($creditRaw)
            );

            $direction = 'credit';
        } elseif ($hasDebit) {
            $amount = abs(
                $this->money($debitRaw)
            );

            $direction = 'debit';
        } else {
            $amountRaw = $get('amount');

            if (!$this->hasValue($amountRaw)) {
                throw new RuntimeException(
                    'La transacción no contiene monto.'
                );
            }

            $signedAmount = $this->money(
                $amountRaw
            );

            $directionRaw = strtolower(
                trim((string) ($get('direction') ?? ''))
            );

            $direction = $this->normalizeDirection(
                $directionRaw,
                $signedAmount
            );

            $amount = abs($signedAmount);
        }

        if ($amount <= 0) {
            throw new RuntimeException(
                'La transacción contiene un monto inválido.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Descripción
        |--------------------------------------------------------------------------
        */

        $descriptionRaw = trim(
            (string) ($get('description') ?? '')
        );

        if ($descriptionRaw === '') {
            throw new RuntimeException(
                'La transacción no contiene descripción.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Moneda
        |--------------------------------------------------------------------------
        */

        $currency = $map['_currency']
            ?? $get('currency')
            ?? 'CLP';

        $currency = strtoupper(
            trim((string) $currency)
        );

        if ($currency === '') {
            $currency = 'CLP';
        }

        /*
        |--------------------------------------------------------------------------
        | Saldo posterior
        |--------------------------------------------------------------------------
        */

        $balanceRaw = $get('balance_after');

        $balanceAfter = null;

        if ($this->hasValue($balanceRaw)) {
            $balanceAfter = $this->money(
                $balanceRaw
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Resultado normalizado
        |--------------------------------------------------------------------------
        */

        return [
            'booking_date' => $bookingDate,

            'value_date' => $valueDate,

            'amount' => $amount,

            'currency' => $currency,

            'direction' => $direction,

            'description_raw' => $descriptionRaw,

            'description_normalized' => $this->text(
                $descriptionRaw
            ),

            'reference' => $this->nullable(
                $get('reference')
            ),

            'operation_number' => $this->operationNumber(
                $get('operation_number')
            ),

            'counterparty_tax_id' => $this->taxId(
                $get('counterparty_tax_id')
            ),

            'counterparty_name' => $this->nullable(
                $get('counterparty_name')
            ),

            'balance_after' => $balanceAfter,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    |
    | Convierte valores monetarios provenientes de:
    |
    | 50000        -> 50000
    | "50000"      -> 50000
    | "50.000"     -> 50000
    | "290.145"    -> 290145
    | "1.234.567"  -> 1234567
    | "1.234,56"   -> 1234.56
    | "1,234.56"   -> 1234.56
    | "$ 50.000"   -> 50000
    |
    */

    private function money(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        /*
         * Si PhpSpreadsheet ya entrega un número real,
         * debemos respetarlo.
         */
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $original = trim((string) $value);

        if ($original === '') {
            return 0.0;
        }

        /*
         * Dejamos solamente:
         *
         * números
         * punto
         * coma
         * signo negativo
         */
        $value = preg_replace(
            '/[^0-9,.\-]/u',
            '',
            $original
        );

        if ($value === null || $value === '') {
            return 0.0;
        }

        $negative = str_starts_with(
            $value,
            '-'
        );

        $value = ltrim(
            $value,
            '-'
        );

        $commaCount = substr_count(
            $value,
            ','
        );

        $dotCount = substr_count(
            $value,
            '.'
        );

        /*
        |--------------------------------------------------------------------------
        | Punto y coma presentes
        |--------------------------------------------------------------------------
        */

        if ($commaCount > 0 && $dotCount > 0) {
            $lastComma = strrpos(
                $value,
                ','
            );

            $lastDot = strrpos(
                $value,
                '.'
            );

            /*
             * 1.234,56
             * Formato chileno/europeo.
             */
            if ($lastComma > $lastDot) {
                $value = str_replace(
                    '.',
                    '',
                    $value
                );

                $value = str_replace(
                    ',',
                    '.',
                    $value
                );
            } else {
                /*
                 * 1,234.56
                 * Formato anglosajón.
                 */
                $value = str_replace(
                    ',',
                    '',
                    $value
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Solamente puntos
        |--------------------------------------------------------------------------
        */

        elseif ($dotCount > 0) {
            /*
             * Un único punto requiere distinguir:
             *
             * 290.145 -> miles
             * 123.45  -> decimal
             */

            if ($dotCount === 1) {
                [$integer, $decimal] = explode(
                    '.',
                    $value,
                    2
                );

                /*
                 * Tres dígitos después del punto:
                 * en cartolas CLP se interpreta como
                 * separador de miles.
                 */
                if (
                    strlen($decimal) === 3
                    && ctype_digit($integer)
                    && ctype_digit($decimal)
                ) {
                    $value = $integer . $decimal;
                }
            } else {
                /*
                 * 1.234.567
                 */
                $parts = explode(
                    '.',
                    $value
                );

                $allThousands = true;

                foreach (
                    array_slice($parts, 1)
                    as $part
                ) {
                    if (strlen($part) !== 3) {
                        $allThousands = false;
                        break;
                    }
                }

                if ($allThousands) {
                    $value = implode(
                        '',
                        $parts
                    );
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Solamente comas
        |--------------------------------------------------------------------------
        */

        elseif ($commaCount > 0) {
            if ($commaCount === 1) {
                [$integer, $decimal] = explode(
                    ',',
                    $value,
                    2
                );

                /*
                 * 50,000 -> miles
                 */
                if (
                    strlen($decimal) === 3
                    && ctype_digit($integer)
                    && ctype_digit($decimal)
                ) {
                    $value = $integer . $decimal;
                } else {
                    /*
                     * 123,45 -> decimal
                     */
                    $value = $integer . '.' . $decimal;
                }
            } else {
                /*
                 * 1,234,567
                 */
                $value = str_replace(
                    ',',
                    '',
                    $value
                );
            }
        }

        if (
            !is_numeric($value)
        ) {
            throw new RuntimeException(
                "No fue posible interpretar el monto: {$original}"
            );
        }

        $result = (float) $value;

        return $negative
            ? -$result
            : $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Fecha
    |--------------------------------------------------------------------------
    */

    private function date(
        mixed $value,
        ?int $statementYear = null
    ): string {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance(
                $value
            )->toDateString();
        }

        /*
         * Fecha serial Excel.
         */
        if (
            is_numeric($value)
            && (float) $value > 1000
        ) {
            try {
                return CarbonImmutable::instance(
                    ExcelDate::excelToDateTimeObject(
                        (float) $value
                    )
                )->toDateString();
            } catch (\Throwable) {
                // Continuamos con otros formatos.
            }
        }

        $value = trim(
            (string) $value
        );

        if ($value === '') {
            throw new RuntimeException(
                'La transacción no contiene fecha.'
            );
        }

        /*
         * Formatos completos.
         */
        $formats = [
            'd/m/Y',
            'd-m-Y',
            'Y-m-d',
            'd.m.Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = CarbonImmutable::createFromFormat(
                    '!' . $format,
                    $value
                );

                if ($date !== false) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                // Probar siguiente formato.
            }
        }

        /*
         * BancoEstado:
         *
         * 17/Ago
         * 19/Ago
         * 01/Sep
         */
        if (
            preg_match(
                '/^(\d{1,2})\/([[:alpha:]áéíóúÁÉÍÓÚ]{3,})$/u',
                $value,
                $matches
            )
        ) {
            $day = (int) $matches[1];

            $monthName = mb_strtolower(
                $matches[2]
            );

            $monthName = strtr(
                $monthName,
                [
                    'á' => 'a',
                    'é' => 'e',
                    'í' => 'i',
                    'ó' => 'o',
                    'ú' => 'u',
                ]
            );

            $months = [
                'ene' => 1,
                'feb' => 2,
                'mar' => 3,
                'abr' => 4,
                'may' => 5,
                'jun' => 6,
                'jul' => 7,
                'ago' => 8,
                'sep' => 9,
                'sept' => 9,
                'oct' => 10,
                'nov' => 11,
                'dic' => 12,
            ];

            if (!isset($months[$monthName])) {
                throw new RuntimeException(
                    "Mes bancario no reconocido: {$value}"
                );
            }

            $year = $statementYear
                ?: (int) now()->format('Y');

            return CarbonImmutable::create(
                $year,
                $months[$monthName],
                $day
            )->toDateString();
        }

        /*
         * Último recurso para fechas estándar.
         */
        try {
            return CarbonImmutable::parse(
                $value
            )->toDateString();
        } catch (\Throwable) {
            throw new RuntimeException(
                "No fue posible interpretar la fecha: {$value}"
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Número de operación
    |--------------------------------------------------------------------------
    */

    private function operationNumber(
        mixed $value
    ): ?string {
        $value = trim(
            (string) $value
        );

        if ($value === '') {
            return null;
        }

        /*
         * Si es un identificador puramente numérico,
         * quitamos separadores visuales.
         *
         * 8,083,883 -> 8083883
         * 8.083.883 -> 8083883
         */
        $withoutSeparators = str_replace(
            [',', '.', ' '],
            '',
            $value
        );

        if (ctype_digit($withoutSeparators)) {
            return $withoutSeparators;
        }

        /*
         * Si un banco utiliza referencias alfanuméricas,
         * conservamos el valor.
         */
        return $value;
    }

    private function normalizeDirection(
        string $direction,
        float $amount
    ): string {
        $direction = mb_strtolower(
            trim($direction)
        );

        $creditValues = [
            'credit',
            'credito',
            'crédito',
            'abono',
            'haber',
            'entrada',
            'ingreso',
        ];

        $debitValues = [
            'debit',
            'debito',
            'débito',
            'cargo',
            'debe',
            'salida',
            'egreso',
        ];

        if (
            in_array(
                $direction,
                $creditValues,
                true
            )
        ) {
            return 'credit';
        }

        if (
            in_array(
                $direction,
                $debitValues,
                true
            )
        ) {
            return 'debit';
        }

        return $amount < 0
            ? 'debit'
            : 'credit';
    }

    private function hasValue(
        mixed $value
    ): bool {
        if ($value === null) {
            return false;
        }

        return trim(
            (string) $value
        ) !== '';
    }

    private function text(
        string $value
    ): string {
        return trim(
            preg_replace(
                '/\s+/u',
                ' ',
                mb_strtoupper($value)
            ) ?? ''
        );
    }

    private function nullable(
        mixed $value
    ): ?string {
        $value = trim(
            (string) $value
        );

        return $value === ''
            ? null
            : $value;
    }

    private function taxId(
        mixed $value
    ): ?string {
        $value = preg_replace(
            '/[^0-9Kk]/',
            '',
            (string) $value
        );

        if ($value === null || $value === '') {
            return null;
        }

        return strtoupper($value);
    }

    private function normalizeColumnName(
        string $value
    ): string {
        $value = mb_strtolower(
            trim($value)
        );

        $value = strtr(
            $value,
            [
                'á' => 'a',
                'é' => 'e',
                'í' => 'i',
                'ó' => 'o',
                'ú' => 'u',
                'ü' => 'u',
                'ñ' => 'n',
                '°' => '',
                'º' => '',
                "\xc2\xa0" => ' ',
            ]
        );

        return preg_replace(
            '/[^a-z0-9]+/',
            '',
            $value
        ) ?? '';
    }
}
