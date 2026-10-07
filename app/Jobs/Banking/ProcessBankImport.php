<?php

namespace App\Jobs\Banking;

use App\Models\BankImport;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\BankTransaction;
use App\Models\TransactionRawData;
use App\Services\Banking\Import\TabularReader;
use App\Services\Banking\Normalization\TransactionNormalizer;
use App\Services\Banking\StatementBalanceCalculator;
use App\Services\Banking\StatementIntegrityValidator;
use App\Services\Banking\TransactionFingerprint;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessBankImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public string $importId,
        public string $path,
        public string $extension,
        public array $columnMap
    ) {
    }

    public function handle(
        TabularReader $reader,
        TransactionNormalizer $normalizer,
        TransactionFingerprint $fingerprint,
        StatementBalanceCalculator $balanceCalculator,
        StatementIntegrityValidator $integrityValidator
    ): void {
        $import = BankImport::withoutGlobalScopes()
            ->findOrFail($this->importId);

        $stats = [
            'rows_total' => 0,
            'rows_imported' => 0,
            'rows_duplicate' => 0,
            'rows_failed' => 0,
        ];

        /*
        |--------------------------------------------------------------------------
        | Movimientos válidos de la cartola
        |--------------------------------------------------------------------------
        |
        | Conservamos tanto movimientos nuevos como movimientos ya conocidos.
        | Una transacción duplicada puede formar parte legítimamente de una
        | nueva cartola superpuesta.
        |
        */

        $statementRows = [];

        $import->update([
            'status' => 'processing',
        ]);

        foreach (
            $reader->rows(
                $this->path,
                $this->extension
            ) as $raw
        ) {
            $stats['rows_total']++;

            try {
                $normalized = $normalizer->normalize(
                    $raw,
                    $this->columnMap
                );

                $fp = $fingerprint->make(
                    $import->bank_account_id,
                    $normalized
                );

                /*
                |--------------------------------------------------------------------------
                | Buscar movimiento existente
                |--------------------------------------------------------------------------
                */

                $bankTransaction =
                    BankTransaction::withoutGlobalScopes()
                        ->where(
                            'organization_id',
                            $import->organization_id
                        )
                        ->where(
                            'bank_account_id',
                            $import->bank_account_id
                        )
                        ->where(
                            'fingerprint',
                            $fp
                        )
                        ->first();

                if ($bankTransaction) {
                    /*
                     * El movimiento ya existe globalmente, pero NO se descarta
                     * de esta cartola.
                     */
                    $stats['rows_duplicate']++;
                } else {
                    /*
                    |--------------------------------------------------------------------------
                    | Crear nuevo movimiento
                    |--------------------------------------------------------------------------
                    */

                    $bankTransaction = DB::transaction(
                        function () use (
                            $import,
                            $normalized,
                            $fp,
                            $raw
                        ): BankTransaction {
                            $transaction =
                                BankTransaction::withoutGlobalScopes()
                                    ->create(
                                        array_merge(
                                            $normalized,
                                            [
                                                'organization_id' =>
                                                    $import->organization_id,

                                                'bank_account_id' =>
                                                    $import->bank_account_id,

                                                /*
                                                 * Compatibilidad temporal.
                                                 * El statement será asociado
                                                 * después de crearlo.
                                                 */
                                                'statement_id' => null,

                                                /*
                                                 * Primera importación que
                                                 * descubrió el movimiento.
                                                 */
                                                'bank_import_id' =>
                                                    $import->id,

                                                'fingerprint' =>
                                                    $fp,

                                                'status' =>
                                                    'normalized',
                                            ]
                                        )
                                    );

                            TransactionRawData::create([
                                'bank_transaction_id' =>
                                    $transaction->getKey(),

                                'payload' =>
                                    $raw,
                            ]);

                            return $transaction;
                        }
                    );

                    $stats['rows_imported']++;
                }

                /*
                |--------------------------------------------------------------------------
                | Aparición del movimiento en ESTA cartola
                |--------------------------------------------------------------------------
                |
                | Guardamos la referencia al BankTransaction independientemente
                | de que sea nuevo o ya existente.
                |
                */

                $statementRows[] = [
                    'transaction_id' =>
                        (string) $bankTransaction->getKey(),

                    'normalized' =>
                        $normalized,

                    /*
                     * position conserva el orden real entregado por el banco.
                     */
                    'position' =>
                        count($statementRows) + 1,

                    /*
                     * TabularReader todavía no expone la fila física original.
                     * Lo incorporaremos posteriormente.
                     */
                    'source_row' =>
                        null,

                    'balance_after_reported' =>
                        $normalized['balance_after'] ?? null,
                ];
            } catch (Throwable $exception) {
                report($exception);

                $stats['rows_failed']++;
            }

            if ($stats['rows_total'] % 100 === 0) {
                $import->update($stats);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Crear la cartola
        |--------------------------------------------------------------------------
        |
        | A diferencia de la implementación anterior, una cartola válida se
        | crea incluso cuando TODOS sus movimientos ya existen.
        |
        | Ejemplo:
        |
        | rows_imported  = 0
        | rows_duplicate = 36
        |
        | La cartola sigue siendo un documento bancario válido.
        |
        */

        $this->createStatement(
            $import,
            $statementRows,
            $balanceCalculator,
            $integrityValidator
        );

        $import->update(
            array_merge(
                $stats,
                [
                    'status' =>
                        $stats['rows_failed'] > 0
                            ? 'completed_with_errors'
                            : 'completed',
                ]
            )
        );
    }

    private function createStatement(
        BankImport $import,
        array $statementRows,
        StatementBalanceCalculator $balanceCalculator,
        StatementIntegrityValidator $integrityValidator
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Orden documental
        |--------------------------------------------------------------------------
        |
        | NO ordenamos por fecha.
        |
        | El orden físico entregado por el banco es relevante porque pueden
        | existir varios movimientos durante el mismo día.
        |
        */

        $firstRow = $statementRows[0];

        $lastRow =
            $statementRows[
                count($statementRows) - 1
            ];

        $firstTransaction =
            $firstRow['normalized'];

        $lastTransaction =
            $lastRow['normalized'];

        $importMetadata =
            $import->metadata ?? [];

        $periodFrom = $this->parseStatementDate(
            $importMetadata['period_from'] ?? null
        );

        $periodTo = $this->parseStatementDate(
            $importMetadata['period_to'] ?? null
        );

        /*
        |--------------------------------------------------------------------------
        | Fallback multi-banco
        |--------------------------------------------------------------------------
        */

        if ($periodFrom === null) {
            $periodFrom =
                $firstTransaction['booking_date'];
        }

        if ($periodTo === null) {
            $periodTo =
                $lastTransaction['booking_date'];
        }

        $openingBalance = null;
        $closingBalance = null;

        if (
            isset($firstTransaction['balance_after'])
            && $firstTransaction['balance_after'] !== null
            && $firstTransaction['balance_after'] !== ''
        ) {
            $openingBalance =
                $balanceCalculator->openingBalance(
                    $firstTransaction['balance_after'],
                    $firstTransaction['amount'],
                    $this->directionValue(
                        $firstTransaction['direction']
                    )
                );
        }

        if (
            isset($lastTransaction['balance_after'])
            && $lastTransaction['balance_after'] !== null
            && $lastTransaction['balance_after'] !== ''
        ) {
            $closingBalance =
                $balanceCalculator->closingBalance(
                    $lastTransaction['balance_after']
                );
        }

        $currency =
            $firstTransaction['currency']
            ?? 'CLP';
        /*
        |--------------------------------------------------------------------------
        | Validación de integridad financiera
        |--------------------------------------------------------------------------
        |
        | Comprobamos la continuidad matemática de los saldos informados
        | por el banco respetando el orden documental original.
        |
        | No modificamos ningún movimiento ni saldo.
        |
        */

        $integrityRows =
            collect($statementRows)
                ->map(
                    function (array $row): array {
                        return [
                            'position' =>
                                $row['position'],

                            'amount' =>
                                $row['normalized']['amount'],

                            'direction' =>
                                $this->directionValue(
                                    $row['normalized']['direction']
                                ),

                            'balance_after_reported' =>
                                $row['balance_after_reported'],
                        ];
                    }
                )
                ->all();

        $integrity =
            $integrityValidator->validate(
                $integrityRows
            );

        $statementStatus =
            $integrity['valid']
                ? 'verified'
                : 'inconsistent';

        DB::transaction(
            function () use (
                $import,
                $importMetadata,
                $statementRows,
                $periodFrom,
                $periodTo,
                $openingBalance,
                $closingBalance,
                $currency,
                $integrity,
                $statementStatus
            ): void {
                /*
                |--------------------------------------------------------------------------
                | BankStatement
                |--------------------------------------------------------------------------
                */

                $statement =
                    BankStatement::withoutGlobalScopes()
                        ->create([
                            'organization_id' =>
                                $import->organization_id,

                            'bank_account_id' =>
                                $import->bank_account_id,

                            'bank_import_id' =>
                                $import->id,

                            'period_from' =>
                                $periodFrom,

                            'period_to' =>
                                $periodTo,

                            'opening_balance' =>
                                $openingBalance,

                            'closing_balance' =>
                                $closingBalance,

                            'currency' =>
                                $currency,

                            'status' =>
                                $statementStatus,

                            'statement_reference' =>
                                $importMetadata[
                                    'statement_reference'
                                ] ?? null,

                            'metadata' => [
                                'detected_format' =>
                                    $importMetadata[
                                        'detected_format'
                                    ] ?? null,

                                'original_name' =>
                                    $importMetadata[
                                        'original_name'
                                    ] ?? null,

                                'source' =>
                                    $import->source,

                                'integrity' =>
                                    $integrity,
                            ],
                        ]);

                /*
                |--------------------------------------------------------------------------
                | Vincular TODOS los movimientos
                |--------------------------------------------------------------------------
                |
                | Incluye:
                |
                | - movimientos recién creados
                | - movimientos ya existentes
                |
                */

                foreach ($statementRows as $row) {
                    BankStatementTransaction::withoutGlobalScopes()
                        ->create([
                            'organization_id' =>
                                $import->organization_id,

                            'bank_statement_id' =>
                                $statement->getKey(),

                            'bank_transaction_id' =>
                                $row['transaction_id'],

                            'position' =>
                                $row['position'],

                            'source_row' =>
                                $row['source_row'],

                            'balance_after_reported' =>
                                $row[
                                    'balance_after_reported'
                                ],
                        ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Compatibilidad LEGACY
                |--------------------------------------------------------------------------
                |
                | Durante la transición mantenemos statement_id para aquellos
                | movimientos que todavía no tengan una cartola legacy.
                |
                | IMPORTANTE:
                | Nunca reemplazamos statement_id de una transacción que ya
                | pertenece a otra cartola.
                |
                */

                $transactionIds =
                    collect($statementRows)
                        ->pluck('transaction_id')
                        ->unique()
                        ->values()
                        ->all();

                BankTransaction::withoutGlobalScopes()
                    ->where(
                        'organization_id',
                        $import->organization_id
                    )
                    ->whereIn(
                        'id',
                        $transactionIds
                    )
                    ->whereNull('statement_id')
                    ->update([
                        'statement_id' =>
                            $statement->getKey(),
                    ]);
            }
        );
    }

    private function parseStatementDate(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat(
                'd/m/Y',
                $value
            )->format('Y-m-d');
        } catch (Throwable) {
            /*
             * Algunos adaptadores pueden entregar
             * directamente yyyy-mm-dd.
             */
        }

        try {
            return Carbon::parse(
                $value
            )->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function directionValue(
        mixed $direction
    ): string {
        if ($direction instanceof \BackedEnum) {
            return (string) $direction->value;
        }

        return (string) $direction;
    }

    public function failed(
        Throwable $exception
    ): void {
        $import = BankImport::withoutGlobalScopes()
            ->find($this->importId);

        if (!$import) {
            return;
        }

        $metadata =
            $import->metadata ?? [];

        /*
         * No almacenamos stack traces ni datos bancarios
         * sensibles en metadata.
         */
        $metadata['error'] =
            'Importación fallida; revise los logs seguros.';

        $import->update([
            'status' =>
                'failed',

            'metadata' =>
                $metadata,
        ]);
    }
}
