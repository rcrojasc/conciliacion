<?php

namespace App\Console\Commands;

use App\Models\BankStatement;
use App\Services\Banking\StatementIntegrityValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ValidateHistoricalBankStatements extends Command
{
    protected $signature = 'banking:validate-statements
                            {--dry-run : Valida sin modificar la base de datos}';

    protected $description =
        'Valida la integridad financiera de cartolas bancarias históricas.';

    public function handle(
        StatementIntegrityValidator $validator
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        $this->newLine();

        $this->info(
            $dryRun
                ? 'ARIONS FINANCE - Simulación de validación histórica'
                : 'ARIONS FINANCE - Validación histórica'
        );

        $this->newLine();

        $statements =
            BankStatement::withoutGlobalScopes()
                ->with([
                    'transactions' => function ($query) {
                        $query->orderByPivot('position');
                    },
                ])
                ->orderBy('created_at')
                ->get();

        if ($statements->isEmpty()) {
            $this->warn(
                'No existen cartolas bancarias para validar.'
            );

            return self::SUCCESS;
        }

        $summary = [
            'checked' => 0,
            'verified' => 0,
            'inconsistent' => 0,
            'without_transactions' => 0,
            'updated' => 0,
        ];

        foreach ($statements as $statement) {
            /** @var BankStatement $statement */

            $summary['checked']++;


            if ($statement->transactions->isEmpty()) {
                $summary['without_transactions']++;

                $this->warn(
                    sprintf(
                        'Cartola %s: sin movimientos N:N. Se omite.',
                        $statement->getKey()
                    )
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Construir secuencia documental
            |--------------------------------------------------------------------------
            */

            $rows =
                $statement->transactions
                    ->map(
                        function ($transaction): array {
                            return [
                                'position' =>
                                    (int) $transaction->pivot->position,

                                'amount' =>
                                    (float) $transaction->amount,

                                'direction' =>
                                    $transaction->direction,

                                'balance_after_reported' =>
                                    $transaction->pivot
                                        ->balance_after_reported,
                            ];
                        }
                    )
                    ->values()
                    ->all();

            /*
            |--------------------------------------------------------------------------
            | Validación interna movimiento a movimiento
            |--------------------------------------------------------------------------
            */

            $integrity =
                $validator->validate($rows);

            /*
            |--------------------------------------------------------------------------
            | Validar saldos de apertura y cierre
            |--------------------------------------------------------------------------
            |
            | Además de comprobar la continuidad entre movimientos,
            | verificamos que:
            |
            | opening_balance + primer movimiento
            |     = primer saldo reportado
            |
            | último saldo reportado
            |     = closing_balance
            |
            */

            $boundaryChecks =
                $this->validateBoundaries(
                    $statement,
                    $rows
                );

            if (!$boundaryChecks['valid']) {
                $integrity['valid'] = false;

                foreach (
                    $boundaryChecks['inconsistencies']
                    as $inconsistency
                ) {
                    $integrity['inconsistencies'][] =
                        $inconsistency;
                }
            }

            $integrity['boundary_checks'] =
                $boundaryChecks;

            $status =
                $integrity['valid']
                    ? 'verified'
                    : 'inconsistent';

            if ($status === 'verified') {
                $summary['verified']++;
            } else {
                $summary['inconsistent']++;
            }

            $this->line(
                sprintf(
                    'Cartola %s | movimientos: %d | resultado: %s | inconsistencias: %d',
                    $statement->getKey(),
                    count($rows),
                    strtoupper($status),
                    count($integrity['inconsistencies'])
                )
            );

            if ($dryRun) {
                continue;
            }

            DB::transaction(
                function () use (
                    $statement,
                    $integrity,
                    $status,
                    &$summary
                ): void {
                    $metadata =
                        $statement->metadata ?? [];

                    $metadata['integrity'] =
                        $integrity;

                    /*
                     * Registramos qué versión lógica realizó
                     * esta validación histórica.
                     */
                    $metadata['integrity_validation'] = [
                        'version' => 1,
                        'source' =>
                            'historical_statement_validation',
                    ];

                    $statement->update([
                        'status' =>
                            $status,

                        'metadata' =>
                            $metadata,
                    ]);

                    $summary['updated']++;
                }
            );
        }

        $this->newLine();

        $this->table(
            [
                'Concepto',
                'Cantidad',
            ],
            [
                [
                    'Cartolas revisadas',
                    $summary['checked'],
                ],
                [
                    'Verificadas',
                    $summary['verified'],
                ],
                [
                    'Inconsistentes',
                    $summary['inconsistent'],
                ],
                [
                    'Sin movimientos N:N',
                    $summary['without_transactions'],
                ],
                [
                    'Actualizadas',
                    $summary['updated'],
                ],
            ]
        );

        $this->newLine();

        if ($dryRun) {
            $this->warn(
                'SIMULACIÓN: no se modificó ningún dato.'
            );
        } else {
            $this->info(
                'Validación histórica finalizada correctamente.'
            );
        }

        return self::SUCCESS;
    }

    private function validateBoundaries(
        BankStatement $statement,
        array $rows
    ): array {
        $inconsistencies = [];

        if (empty($rows)) {
            return [
                'valid' => true,
                'inconsistencies' => [],
            ];
        }

        $first =
            $rows[0];

        $last =
            $rows[count($rows) - 1];

        /*
        |--------------------------------------------------------------------------
        | Saldo inicial
        |--------------------------------------------------------------------------
        */

        if (
            $statement->opening_balance !== null
            && $first['balance_after_reported'] !== null
        ) {
            $openingBalance =
                (float) $statement->opening_balance;

            $amount =
                abs((float) $first['amount']);

            $direction =
                $this->directionValue(
                    $first['direction']
                );

            $expectedFirstBalance =
                match ($direction) {
                    'credit' =>
                        $openingBalance + $amount,

                    'debit' =>
                        $openingBalance - $amount,

                    default =>
                        null,
                };

            if (
                $expectedFirstBalance !== null
                && abs(
                    (float) $first['balance_after_reported']
                    - $expectedFirstBalance
                ) > 0.01
            ) {
                $inconsistencies[] = [
                    'position' => 1,
                    'type' =>
                        'opening_balance_mismatch',
                    'opening_balance' =>
                        $openingBalance,
                    'amount' =>
                        $amount,
                    'direction' =>
                        $direction,
                    'expected_balance' =>
                        $expectedFirstBalance,
                    'reported_balance' =>
                        (float) $first[
                            'balance_after_reported'
                        ],
                    'difference' =>
                        (float) $first[
                            'balance_after_reported'
                        ]
                        - $expectedFirstBalance,
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Saldo final
        |--------------------------------------------------------------------------
        */

        if (
            $statement->closing_balance !== null
            && $last['balance_after_reported'] !== null
        ) {
            $closingBalance =
                (float) $statement->closing_balance;

            $reportedClosingBalance =
                (float) $last[
                    'balance_after_reported'
                ];

            if (
                abs(
                    $reportedClosingBalance
                    - $closingBalance
                ) > 0.01
            ) {
                $inconsistencies[] = [
                    'position' =>
                        $last['position'],

                    'type' =>
                        'closing_balance_mismatch',

                    'expected_balance' =>
                        $closingBalance,

                    'reported_balance' =>
                        $reportedClosingBalance,

                    'difference' =>
                        $reportedClosingBalance
                        - $closingBalance,
                ];
            }
        }

        return [
            'valid' =>
                empty($inconsistencies),

            'inconsistencies' =>
                $inconsistencies,
        ];
    }

    private function directionValue(
        mixed $direction
    ): string {
        if ($direction instanceof \BackedEnum) {
            return (string) $direction->value;
        }

        return strtolower(
            trim((string) $direction)
        );
    }
}
