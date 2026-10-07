<?php

namespace App\Console\Commands;

use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\BankTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class BackfillBankStatementTransactions extends Command
{
    protected $signature = 'banking:backfill-statement-transactions
                            {--dry-run : Analiza sin modificar la base de datos}';

    protected $description =
        'Reconstruye de forma segura las relaciones N:N de cartolas históricas usando statement_id legacy.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->newLine();

        $this->info(
            $dryRun
                ? 'ARIONS FINANCE - Simulación de backfill'
                : 'ARIONS FINANCE - Backfill de cartolas históricas'
        );

        $this->newLine();

        $statements =
            BankStatement::withoutGlobalScopes()
                ->orderBy('created_at')
                ->get();

        if ($statements->isEmpty()) {
            $this->warn(
                'No existen cartolas bancarias.'
            );

            return self::SUCCESS;
        }

        $summary = [
            'statements_checked' => 0,
            'legacy_transactions' => 0,
            'existing_links' => 0,
            'links_to_create' => 0,
            'links_created' => 0,
        ];

        foreach ($statements as $statement) {
            $summary['statements_checked']++;

            /*
            |--------------------------------------------------------------------------
            | Movimientos asociados mediante el modelo legacy
            |--------------------------------------------------------------------------
            */

            $legacyTransactions =
                BankTransaction::withoutGlobalScopes()
                    ->where(
                        'organization_id',
                        $statement->organization_id
                    )
                    ->where(
                        'bank_account_id',
                        $statement->bank_account_id
                    )
                    ->where(
                        'statement_id',
                        $statement->getKey()
                    )
                    ->orderBy('id')
                    ->get();

            $legacyCount =
                $legacyTransactions->count();

            $existingCount =
                BankStatementTransaction::withoutGlobalScopes()
                    ->where(
                        'bank_statement_id',
                        $statement->getKey()
                    )
                    ->count();

            $summary['legacy_transactions'] +=
                $legacyCount;

            $summary['existing_links'] +=
                $existingCount;

            /*
            |--------------------------------------------------------------------------
            | Determinar qué relaciones faltan
            |--------------------------------------------------------------------------
            */

            $existingTransactionIds =
                BankStatementTransaction::withoutGlobalScopes()
                    ->where(
                        'bank_statement_id',
                        $statement->getKey()
                    )
                    ->pluck('bank_transaction_id')
                    ->all();

            $missingTransactions =
                $legacyTransactions
                    ->reject(
                        fn (BankTransaction $transaction) =>
                            in_array(
                                $transaction->getKey(),
                                $existingTransactionIds,
                                true
                            )
                    )
                    ->values();

            $missingCount =
                $missingTransactions->count();

            $summary['links_to_create'] +=
                $missingCount;

            $this->line(
                sprintf(
                    'Cartola %s | legacy: %d | N:N existentes: %d | faltantes: %d',
                    $statement->getKey(),
                    $legacyCount,
                    $existingCount,
                    $missingCount
                )
            );

            if (
                $dryRun
                || $missingTransactions->isEmpty()
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Posición inicial
            |--------------------------------------------------------------------------
            |
            | Si ya existen relaciones, continuamos desde la posición mayor.
            |
            */

            $nextPosition =
                (
                    (int) BankStatementTransaction::withoutGlobalScopes()
                        ->where(
                            'bank_statement_id',
                            $statement->getKey()
                        )
                        ->max('position')
                ) + 1;

            DB::transaction(
                function () use (
                    $statement,
                    $missingTransactions,
                    &$nextPosition,
                    &$summary
                ): void {
                    foreach (
                        $missingTransactions as $transaction
                    ) {
                        /*
                         * Comprobación adicional para mantener
                         * el proceso idempotente.
                         */
                        $alreadyExists =
                            BankStatementTransaction::withoutGlobalScopes()
                                ->where(
                                    'bank_statement_id',
                                    $statement->getKey()
                                )
                                ->where(
                                    'bank_transaction_id',
                                    $transaction->getKey()
                                )
                                ->exists();

                        if ($alreadyExists) {
                            continue;
                        }

                        BankStatementTransaction::withoutGlobalScopes()
                            ->create([
                                'organization_id' =>
                                    $statement->organization_id,

                                'bank_statement_id' =>
                                    $statement->getKey(),

                                'bank_transaction_id' =>
                                    $transaction->getKey(),

                                'position' =>
                                    $nextPosition,

                                /*
                                 * La implementación histórica no
                                 * conservó la fila física del Excel.
                                 */
                                'source_row' =>
                                    null,

                                /*
                                 * Este valor sí existe en la
                                 * transacción normalizada histórica.
                                 */
                                'balance_after_reported' =>
                                    $transaction->balance_after,
                            ]);

                        $nextPosition++;

                        $summary['links_created']++;
                    }
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
                    $summary['statements_checked'],
                ],
                [
                    'Movimientos legacy',
                    $summary['legacy_transactions'],
                ],
                [
                    'Relaciones N:N existentes',
                    $summary['existing_links'],
                ],
                [
                    'Relaciones faltantes',
                    $summary['links_to_create'],
                ],
                [
                    'Relaciones creadas',
                    $summary['links_created'],
                ],
            ]
        );

        $this->newLine();

        if ($dryRun) {
            $this->warn(
                'SIMULACIÓN: no se modificó ningún dato.'
            );

            return self::SUCCESS;
        }

        $this->info(
            'Backfill finalizado correctamente.'
        );

        return self::SUCCESS;
    }
}
