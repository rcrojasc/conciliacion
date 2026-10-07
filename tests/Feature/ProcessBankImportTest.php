<?php

namespace Tests\Feature;

use App\Jobs\Banking\ProcessBankImport;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\BankImport;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\BankTransaction;
use App\Models\Organization;
use App\Models\TransactionRawData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProcessBankImportTest extends TestCase
{
    use RefreshDatabase;

    private string $tempFile;

    private Organization $organization;

    private Bank $bank;

    private BankAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::query()->create([
            'name' => 'ARIONS TEST',
            'tax_id' => '76.000.000-0',
            'status' => 'active',
            'base_currency' => 'CLP',
            'timezone' => 'America/Santiago',
        ]);

        $this->bank = Bank::query()->create([
            'code' => 'TEST',
            'name' => 'Banco de Pruebas',
            'country' => 'CL',
            'status' => 'active',
        ]);

        $this->account = BankAccount::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->getKey(),
            'bank_id' => $this->bank->getKey(),
            'name' => 'Cuenta Corriente Test',
            'account_type' => 'checking',
            'currency' => 'CLP',
            'masked_number' => '****1234',
            'status' => 'active',
        ]);

        $this->tempFile = tempnam(
            sys_get_temp_dir(),
            'arions_statement_'
        ) . '.xlsx';

        $this->createTestStatement();
    }

    protected function tearDown(): void
    {
        if (
            isset($this->tempFile)
            && file_exists($this->tempFile)
        ) {
            unlink($this->tempFile);
        }

        parent::tearDown();
    }

    public function test_processes_bank_import_and_creates_statement(): void
    {
        $import = $this->createImport(
            'cartola-test.xlsx',
            'first-test-import',
            '17/08/2026',
            '18/08/2026'
        );

        $this->processImport($import);

        $import->refresh();

        $this->assertSame(
            'completed',
            $import->status
        );

        $this->assertSame(
            3,
            $import->rows_total
        );

        $this->assertSame(
            3,
            $import->rows_imported
        );

        $this->assertSame(
            0,
            $import->rows_duplicate
        );

        $this->assertSame(
            0,
            $import->rows_failed
        );

        /*
        |--------------------------------------------------------------------------
        | Entidades principales
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount(
            'bank_transactions',
            3
        );

        $this->assertDatabaseCount(
            'transaction_raw_data',
            3
        );

        $this->assertDatabaseCount(
            'bank_statements',
            1
        );

        /*
         * Nueva arquitectura:
         * cada movimiento aparece una vez en la cartola.
         */
        $this->assertDatabaseCount(
            'bank_statement_transactions',
            3
        );

        $statement = BankStatement::withoutGlobalScopes()
            ->firstOrFail();

        $this->assertSame(
            $this->organization->getKey(),
            $statement->organization_id
        );

        $this->assertSame(
            $this->account->getKey(),
            $statement->bank_account_id
        );

        /*
         * La cartola conoce ahora qué importación la originó.
         */
        $this->assertSame(
            $import->getKey(),
            $statement->bank_import_id
        );

        $this->assertSame(
            'verified',
            $statement->status
        );

        $this->assertSame(
            '2026-08-17',
            \Carbon\Carbon::parse(
                $statement->period_from
            )->toDateString()
        );

        $this->assertSame(
            '2026-08-18',
            \Carbon\Carbon::parse(
                $statement->period_to
            )->toDateString()
        );

        $this->assertSame(
            'CLP',
            trim($statement->currency)
        );

        /*
         * Primer movimiento:
         *
         * Cargo = 470
         * Saldo posterior = 264.104
         *
         * Saldo inicial:
         * 264.104 + 470 = 264.574
         */
        $this->assertEquals(
            264574.0,
            (float) $statement->opening_balance
        );

        /*
         * Último movimiento:
         *
         * Cargo = 10.000
         * Saldo posterior = 304.104
         */
        $this->assertEquals(
            304104.0,
            (float) $statement->closing_balance
        );

        /*
        |--------------------------------------------------------------------------
        | Relaciones N:N
        |--------------------------------------------------------------------------
        */

        $statement->refresh();

        $this->assertCount(
            3,
            $statement->transactions
        );

        /*
         * El orden de la cartola debe conservarse.
         */
        $this->assertSame(
            [
                1,
                2,
                3,
            ],
            $statement->transactions
                ->pluck('pivot.position')
                ->map(fn ($position) => (int) $position)
                ->values()
                ->all()
        );

        /*
         * Verificamos los saldos reportados por la propia cartola.
         */
        $this->assertSame(
            [
                264104.0,
                314104.0,
                304104.0,
            ],
            $statement->transactions
                ->pluck('pivot.balance_after_reported')
                ->map(fn ($balance) => (float) $balance)
                ->values()
                ->all()
        );

        /*
        |--------------------------------------------------------------------------
        | Compatibilidad legacy
        |--------------------------------------------------------------------------
        |
        | Durante la transición los movimientos nuevos siguen recibiendo
        | statement_id.
        */

        $transactions =
            BankTransaction::withoutGlobalScopes()
                ->get();

        $this->assertCount(
            3,
            $transactions
        );

        foreach ($transactions as $transaction) {
            $this->assertSame(
                $import->getKey(),
                $transaction->bank_import_id
            );

            $this->assertSame(
                $statement->getKey(),
                $transaction->statement_id
            );

            $this->assertNotEmpty(
                $transaction->fingerprint
            );
        }

        $this->assertSame(
            3,
            TransactionRawData::query()->count()
        );
    }

    public function test_reuses_transactions_in_second_statement_without_duplication(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Primera cartola
        |--------------------------------------------------------------------------
        */

        $firstImport = $this->createImport(
            'cartola-original.xlsx',
            'first-import-' . $this->tempFile,
            '17/08/2026',
            '18/08/2026'
        );

        $this->processImport(
            $firstImport
        );

        $firstImport->refresh();

        $this->assertSame(
            'completed',
            $firstImport->status
        );

        $this->assertSame(
            3,
            $firstImport->rows_imported
        );

        $this->assertSame(
            0,
            $firstImport->rows_duplicate
        );

        $this->assertDatabaseCount(
            'bank_transactions',
            3
        );

        $this->assertDatabaseCount(
            'bank_statements',
            1
        );

        $this->assertDatabaseCount(
            'bank_statement_transactions',
            3
        );

        $firstStatement =
            BankStatement::withoutGlobalScopes()
                ->where(
                    'bank_import_id',
                    $firstImport->getKey()
                )
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Segunda cartola
        |--------------------------------------------------------------------------
        |
        | Simulamos un documento bancario diferente mediante un file_hash
        | distinto, pero sus tres movimientos internos son exactamente los
        | mismos.
        |
        | El fingerprint debe impedir duplicar BankTransaction.
        |
        | Sin embargo, el documento BankStatement SÍ debe existir.
        */

        $secondImport = $this->createImport(
            'cartola-repetida.xlsx',
            'second-import-' . $this->tempFile,
            '17/08/2026',
            '18/08/2026'
        );

        $this->processImport(
            $secondImport
        );

        $secondImport->refresh();

        $this->assertSame(
            'completed',
            $secondImport->status
        );

        $this->assertSame(
            3,
            $secondImport->rows_total
        );

        /*
         * Ninguna BankTransaction nueva.
         */
        $this->assertSame(
            0,
            $secondImport->rows_imported
        );

        /*
         * Los tres movimientos fueron reconocidos.
         */
        $this->assertSame(
            3,
            $secondImport->rows_duplicate
        );

        $this->assertSame(
            0,
            $secondImport->rows_failed
        );

        /*
        |--------------------------------------------------------------------------
        | Resultado esperado N:N
        |--------------------------------------------------------------------------
        |
        | BankTransaction:
        | siguen siendo solamente 3.
        |
        | BankStatement:
        | ahora existen 2 documentos.
        |
        | Pivot:
        | 3 apariciones en cada documento = 6.
        */

        $this->assertDatabaseCount(
            'bank_transactions',
            3
        );

        $this->assertDatabaseCount(
            'bank_statements',
            2
        );

        $this->assertDatabaseCount(
            'bank_statement_transactions',
            6
        );

        /*
         * RAW representa la primera captura del movimiento único.
         * No lo duplicamos al reutilizar BankTransaction.
         */
        $this->assertDatabaseCount(
            'transaction_raw_data',
            3
        );

        $secondStatement =
            BankStatement::withoutGlobalScopes()
                ->where(
                    'bank_import_id',
                    $secondImport->getKey()
                )
                ->firstOrFail();

        $this->assertNotSame(
            $firstStatement->getKey(),
            $secondStatement->getKey()
        );

        /*
         * Ambas cartolas contienen tres movimientos.
         */
        $this->assertCount(
            3,
            $firstStatement->transactions
        );

        $this->assertCount(
            3,
            $secondStatement->transactions
        );

        /*
         * Los IDs deben ser exactamente los mismos:
         * dos documentos reutilizan las mismas BankTransaction.
         */

        $firstTransactionIds =
            $firstStatement->transactions
                ->pluck('id')
                ->sort()
                ->values()
                ->all();

        $secondTransactionIds =
            $secondStatement->transactions
                ->pluck('id')
                ->sort()
                ->values()
                ->all();

        $this->assertSame(
            $firstTransactionIds,
            $secondTransactionIds
        );

        /*
         * Cada movimiento debe indicar que pertenece a dos cartolas
         * mediante la nueva relación N:N.
         */
        $transactions =
            BankTransaction::withoutGlobalScopes()
                ->get();

        foreach ($transactions as $transaction) {
            $transaction->load('statements');

            $this->assertCount(
                2,
                $transaction->statements
            );

            /*
             * bank_import_id conserva la procedencia ORIGINAL:
             * la primera importación que creó el movimiento.
             */
            $this->assertSame(
                $firstImport->getKey(),
                $transaction->bank_import_id
            );

            /*
             * statement_id también conserva temporalmente la primera
             * asociación legacy. La segunda cartola NO debe sobrescribirla.
             */
            $this->assertSame(
                $firstStatement->getKey(),
                $transaction->statement_id
            );
        }
    }

    public function test_partially_overlapping_statements_reuse_existing_and_create_new_transactions(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CARTOLA A
        |--------------------------------------------------------------------------
        */

        $firstImport = $this->createImport(
            'cartola-a.xlsx',
            'partial-overlap-first',
            '17/08/2026',
            '18/08/2026'
        );

        $this->processImport(
            $firstImport
        );

        $firstImport->refresh();

        $this->assertSame(
            'completed',
            $firstImport->status
        );

        $this->assertSame(
            3,
            $firstImport->rows_imported
        );

        $this->assertSame(
            0,
            $firstImport->rows_duplicate
        );

        /*
        |--------------------------------------------------------------------------
        | Crear CARTOLA B
        |--------------------------------------------------------------------------
        */

        $secondFile = tempnam(
            sys_get_temp_dir(),
            'arions_statement_overlap_'
        ) . '.xlsx';

        try {
            $this->createOverlappingStatement(
                $secondFile
            );

            $secondImport = $this->createImport(
                'cartola-b.xlsx',
                'partial-overlap-second',
                '18/08/2026',
                '20/08/2026'
            );

            $this->processImportFile(
                $secondImport,
                $secondFile
            );

            $secondImport->refresh();

            /*
            |--------------------------------------------------------------------------
            | Estadísticas segunda importación
            |--------------------------------------------------------------------------
            */

            $this->assertSame(
                'completed',
                $secondImport->status
            );

            $this->assertSame(
                3,
                $secondImport->rows_total
            );

            /*
            * Dos movimientos son nuevos.
            */
            $this->assertSame(
                2,
                $secondImport->rows_imported
            );

            /*
            * El movimiento 9000001 ya existía.
            */
            $this->assertSame(
                1,
                $secondImport->rows_duplicate
            );

            $this->assertSame(
                0,
                $secondImport->rows_failed
            );

            /*
            |--------------------------------------------------------------------------
            | Totales globales
            |--------------------------------------------------------------------------
            */

            $this->assertDatabaseCount(
                'bank_imports',
                2
            );

            $this->assertDatabaseCount(
                'bank_statements',
                2
            );

            /*
            * 3 de la primera cartola
            * +
            * 2 realmente nuevos
            * =
            * 5 movimientos únicos.
            */
            $this->assertDatabaseCount(
                'bank_transactions',
                5
            );

            /*
            * RAW solamente para movimientos únicos.
            */
            $this->assertDatabaseCount(
                'transaction_raw_data',
                5
            );

            /*
            * Cada cartola contiene tres movimientos.
            *
            * 3 + 3 = 6 apariciones documentales.
            */
            $this->assertDatabaseCount(
                'bank_statement_transactions',
                6
            );

            /*
            |--------------------------------------------------------------------------
            | Recuperar las dos cartolas
            |--------------------------------------------------------------------------
            */

            $firstStatement =
                BankStatement::withoutGlobalScopes()
                    ->where(
                        'bank_import_id',
                        $firstImport->getKey()
                    )
                    ->firstOrFail();

            $secondStatement =
                BankStatement::withoutGlobalScopes()
                    ->where(
                        'bank_import_id',
                        $secondImport->getKey()
                    )
                    ->firstOrFail();

            $this->assertCount(
                3,
                $firstStatement->transactions
            );

            $this->assertCount(
                3,
                $secondStatement->transactions
            );

            /*
            |--------------------------------------------------------------------------
            | Movimiento compartido
            |--------------------------------------------------------------------------
            */

            $sharedTransaction =
                BankTransaction::withoutGlobalScopes()
                    ->where(
                        'operation_number',
                        '9000001'
                    )
                    ->firstOrFail();

            $sharedTransaction->load(
                'statements'
            );

            /*
            * Una sola BankTransaction...
            */
            $this->assertSame(
                1,
                BankTransaction::withoutGlobalScopes()
                    ->where(
                        'operation_number',
                        '9000001'
                    )
                    ->count()
            );

            /*
            * ...pero pertenece a dos cartolas.
            */
            $this->assertCount(
                2,
                $sharedTransaction->statements
            );

            $statementIds =
                $sharedTransaction->statements
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->all();

            $expectedStatementIds =
                collect([
                    $firstStatement->getKey(),
                    $secondStatement->getKey(),
                ])
                    ->sort()
                    ->values()
                    ->all();

            $this->assertSame(
                $expectedStatementIds,
                $statementIds
            );

            /*
            |--------------------------------------------------------------------------
            | Trazabilidad
            |--------------------------------------------------------------------------
            |
            | El movimiento compartido conserva como bank_import_id la
            | importación que originalmente lo creó.
            */

            $this->assertSame(
                $firstImport->getKey(),
                $sharedTransaction->bank_import_id
            );

            /*
            * Los dos movimientos realmente nuevos deben pertenecer
            * originalmente a la segunda importación.
            */

            $newTransactions =
                BankTransaction::withoutGlobalScopes()
                    ->whereIn(
                        'operation_number',
                        [
                            '9000002',
                            '9000003',
                        ]
                    )
                    ->get();

            $this->assertCount(
                2,
                $newTransactions
            );

            foreach ($newTransactions as $transaction) {
                $this->assertSame(
                    $secondImport->getKey(),
                    $transaction->bank_import_id
                );

                $this->assertCount(
                    1,
                    $transaction->statements
                );

                $this->assertSame(
                    $secondStatement->getKey(),
                    $transaction->statements->first()->getKey()
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Orden documental de CARTOLA B
            |--------------------------------------------------------------------------
            */

            $secondStatement->refresh();

            $this->assertSame(
                [
                    '9000001',
                    '9000002',
                    '9000003',
                ],
                $secondStatement->transactions
                    ->pluck('operation_number')
                    ->values()
                    ->all()
            );

            $this->assertSame(
                [
                    1,
                    2,
                    3,
                ],
                $secondStatement->transactions
                    ->pluck('pivot.position')
                    ->map(
                        fn ($position) => (int) $position
                    )
                    ->values()
                    ->all()
            );
        } finally {
            if (file_exists($secondFile)) {
                unlink($secondFile);
            }
        }
    }

    private function createImport(
        string $originalName,
        string $hashSeed,
        string $periodFrom,
        string $periodTo
    ): BankImport {
        return BankImport::withoutGlobalScopes()->create([
            'organization_id' =>
                $this->organization->getKey(),

            'bank_account_id' =>
                $this->account->getKey(),

            'source' =>
                'xlsx',

            'file_hash' =>
                hash(
                    'sha256',
                    $hashSeed
                ),

            'status' =>
                'pending',

            'rows_total' =>
                0,

            'rows_imported' =>
                0,

            'rows_duplicate' =>
                0,

            'rows_failed' =>
                0,

            'metadata' => [
                'original_name' =>
                    $originalName,

                'extension' =>
                    'xlsx',

                'detected_format' =>
                    'test',

                'period_from' =>
                    $periodFrom,

                'period_to' =>
                    $periodTo,

                'statement_year' =>
                    2026,
            ],
        ]);
    }

    public function test_marks_statement_as_inconsistent_when_reported_balance_is_wrong(): void
    {
        $file = tempnam(
            sys_get_temp_dir(),
            'arions_inconsistent_'
        ) . '.xlsx';

        try {
            $this->createInconsistentStatement($file);

            $import = $this->createImport(
                'cartola-inconsistente.xlsx',
                'inconsistent-statement-hash',
                '17/08/2026',
                '18/08/2026'
            );

            $this->processImportFile(
                $import,
                $file
            );

            $import->refresh();

            $this->assertSame(
                'completed',
                $import->status
            );

            $this->assertSame(
                3,
                $import->rows_total
            );

            $this->assertSame(
                3,
                $import->rows_imported
            );

            $this->assertSame(
                0,
                $import->rows_failed
            );

            $statement =
                BankStatement::withoutGlobalScopes()
                    ->where(
                        'bank_import_id',
                        $import->getKey()
                    )
                    ->firstOrFail();

            /*
            * La importación es técnicamente correcta,
            * pero la cartola no cuadra financieramente.
            */
            $this->assertSame(
                'inconsistent',
                $statement->status
            );

            $metadata =
                $statement->metadata;

            $this->assertIsArray(
                $metadata
            );

            $this->assertArrayHasKey(
                'integrity',
                $metadata
            );

            $integrity =
                $metadata['integrity'];

            $this->assertFalse(
                $integrity['valid']
            );

            $this->assertSame(
                2,
                $integrity['checked_transitions']
            );

            $this->assertCount(
                1,
                $integrity['inconsistencies']
            );

            $error =
                $integrity['inconsistencies'][0];

            $this->assertSame(
                'balance_mismatch',
                $error['type']
            );

            $this->assertSame(
                2,
                $error['position']
            );

            $this->assertEquals(
                314104.0,
                (float) $error['expected_balance']
            );

            $this->assertEquals(
                314204.0,
                (float) $error['reported_balance']
            );

            $this->assertEquals(
                100.0,
                (float) $error['difference']
            );

            /*
            * Fundamental:
            * ARIONS conserva el saldo informado por el banco.
            */
            $link =
                BankStatementTransaction::withoutGlobalScopes()
                    ->where(
                        'bank_statement_id',
                        $statement->getKey()
                    )
                    ->where(
                        'position',
                        2
                    )
                    ->firstOrFail();

            $this->assertEquals(
                314204.0,
                (float) $link->balance_after_reported
            );

            /*
            * La cartola inconsistente sigue conservando
            * todos sus movimientos.
            */
            $this->assertSame(
                3,
                BankStatementTransaction::withoutGlobalScopes()
                    ->where(
                        'bank_statement_id',
                        $statement->getKey()
                    )
                    ->count()
            );
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    private function processImport(
        BankImport $import
    ): void {
        $columnMap = [
            'booking_date' =>
                'Fecha',

            'operation_number' =>
                'N° Operación',

            'description' =>
                'Descripción',

            'credit_amount' =>
                'Abonos',

            'debit_amount' =>
                'Cargos',

            'balance_after' =>
                'Saldo',

            '_currency' =>
                'CLP',

            '_statement_year' =>
                2026,
        ];

        $job = new ProcessBankImport(
            $import->getKey(),
            $this->tempFile,
            'xlsx',
            $columnMap
        );

        app()->call([
            $job,
            'handle',
        ]);
    }
    private function processImportFile( BankImport $import, string $file ): void
    {
        $columnMap = [
            'booking_date' =>
                'Fecha',

            'operation_number' =>
                'N° Operación',

            'description' =>
                'Descripción',

            'credit_amount' =>
                'Abonos',

            'debit_amount' =>
                'Cargos',

            'balance_after' =>
                'Saldo',

            '_currency' =>
                'CLP',

            '_statement_year' =>
                2026,
        ];

        $job = new ProcessBankImport(
            $import->getKey(),
            $file,
            'xlsx',
            $columnMap
        );

        app()->call([
            $job,
            'handle',
        ]);
    }

    private function createTestStatement(): void
    {
        $spreadsheet =
            new Spreadsheet();

        $sheet =
            $spreadsheet->getActiveSheet();

        /*
        |--------------------------------------------------------------------------
        | Encabezados
        |--------------------------------------------------------------------------
        */

        $sheet->setCellValue(
            'A1',
            'Cartola bancaria de prueba'
        );

        $sheet->setCellValue(
            'A2',
            'Fecha'
        );

        $sheet->setCellValue(
            'B2',
            'N° Operación'
        );

        $sheet->setCellValue(
            'C2',
            'Descripción'
        );

        $sheet->setCellValue(
            'D2',
            'Abonos'
        );

        $sheet->setCellValue(
            'E2',
            'Cargos'
        );

        $sheet->setCellValue(
            'F2',
            'Saldo'
        );

        /*
        |--------------------------------------------------------------------------
        | Movimiento 1
        |--------------------------------------------------------------------------
        |
        | 264.574 - 470 = 264.104
        */

        $sheet->setCellValue(
            'A3',
            '17/Ago'
        );

        $sheet->setCellValue(
            'B3',
            '8083883'
        );

        $sheet->setCellValue(
            'C3',
            'COMISION TRANSACCION INTERNACIONAL'
        );

        $sheet->setCellValue(
            'D3',
            ''
        );

        $sheet->setCellValue(
            'E3',
            470
        );

        $sheet->setCellValue(
            'F3',
            264104
        );

        /*
        |--------------------------------------------------------------------------
        | Movimiento 2
        |--------------------------------------------------------------------------
        |
        | 264.104 + 50.000 = 314.104
        */

        $sheet->setCellValue(
            'A4',
            '17/Ago'
        );

        $sheet->setCellValue(
            'B4',
            '8171580'
        );

        $sheet->setCellValue(
            'C4',
            'TRANSFERENCIA RECIBIDA'
        );

        $sheet->setCellValue(
            'D4',
            50000
        );

        $sheet->setCellValue(
            'E4',
            ''
        );

        $sheet->setCellValue(
            'F4',
            314104
        );

        /*
        |--------------------------------------------------------------------------
        | Movimiento 3
        |--------------------------------------------------------------------------
        |
        | 314.104 - 10.000 = 304.104
        */

        $sheet->setCellValue(
            'A5',
            '18/Ago'
        );

        $sheet->setCellValue(
            'B5',
            '9000001'
        );

        $sheet->setCellValue(
            'C5',
            'PAGO SERVICIO'
        );

        $sheet->setCellValue(
            'D5',
            ''
        );

        $sheet->setCellValue(
            'E5',
            10000
        );

        $sheet->setCellValue(
            'F5',
            304104
        );

        /*
         * El formato visual de Excel no debe modificar
         * los valores RAW utilizados por el importador.
         */
        $sheet->getStyle(
            'D3:F5'
        )
            ->getNumberFormat()
            ->setFormatCode(
                '#,##0;(#,##0)'
            );

        $writer =
            new Xlsx(
                $spreadsheet
            );

        $writer->save(
            $this->tempFile
        );

        $spreadsheet->disconnectWorksheets();

        unset($spreadsheet);
    }

    private function createOverlappingStatement(
    string $file
): void {
    $spreadsheet = new Spreadsheet();

    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setCellValue(
        'A1',
        'Cartola bancaria superpuesta'
    );

    $sheet->setCellValue('A2', 'Fecha');
    $sheet->setCellValue('B2', 'N° Operación');
    $sheet->setCellValue('C2', 'Descripción');
    $sheet->setCellValue('D2', 'Abonos');
    $sheet->setCellValue('E2', 'Cargos');
    $sheet->setCellValue('F2', 'Saldo');

    /*
    |--------------------------------------------------------------------------
    | Movimiento existente
    |--------------------------------------------------------------------------
    |
    | Es exactamente el movimiento 9000001 de la primera cartola.
    */

    $sheet->setCellValue('A3', '18/Ago');
    $sheet->setCellValue('B3', '9000001');
    $sheet->setCellValue('C3', 'PAGO SERVICIO');
    $sheet->setCellValue('D3', '');
    $sheet->setCellValue('E3', 10000);
    $sheet->setCellValue('F3', 304104);

    /*
    |--------------------------------------------------------------------------
    | Movimiento nuevo
    |--------------------------------------------------------------------------
    |
    | 304.104 + 25.000 = 329.104
    */

    $sheet->setCellValue('A4', '19/Ago');
    $sheet->setCellValue('B4', '9000002');
    $sheet->setCellValue(
        'C4',
        'TRANSFERENCIA RECIBIDA'
    );
    $sheet->setCellValue('D4', 25000);
    $sheet->setCellValue('E4', '');
    $sheet->setCellValue('F4', 329104);

    /*
    |--------------------------------------------------------------------------
    | Movimiento nuevo
    |--------------------------------------------------------------------------
    |
    | 329.104 - 5.000 = 324.104
    */

    $sheet->setCellValue('A5', '20/Ago');
    $sheet->setCellValue('B5', '9000003');
    $sheet->setCellValue(
        'C5',
        'PAGO COMERCIO'
    );
    $sheet->setCellValue('D5', '');
    $sheet->setCellValue('E5', 5000);
    $sheet->setCellValue('F5', 324104);

    $sheet->getStyle('D3:F5')
        ->getNumberFormat()
        ->setFormatCode(
            '#,##0;(#,##0)'
        );

    $writer = new Xlsx(
        $spreadsheet
    );

    $writer->save(
        $file
    );

    $spreadsheet->disconnectWorksheets();

    unset($spreadsheet);
}
private function createInconsistentStatement(
    string $file
    ): void {
        $spreadsheet =
            new Spreadsheet();

        $sheet =
            $spreadsheet->getActiveSheet();

        $sheet->setCellValue(
            'A1',
            'Cartola bancaria inconsistente'
        );

        $sheet->setCellValue('A2', 'Fecha');
        $sheet->setCellValue('B2', 'N° Operación');
        $sheet->setCellValue('C2', 'Descripción');
        $sheet->setCellValue('D2', 'Abonos');
        $sheet->setCellValue('E2', 'Cargos');
        $sheet->setCellValue('F2', 'Saldo');

        /*
        * Movimiento 1
        *
        * Saldo posterior: 264.104
        */
        $sheet->setCellValue('A3', '17/Ago');
        $sheet->setCellValue('B3', '9100001');
        $sheet->setCellValue(
            'C3',
            'MOVIMIENTO INICIAL'
        );
        $sheet->setCellValue('D3', '');
        $sheet->setCellValue('E3', 470);
        $sheet->setCellValue('F3', 264104);

        /*
        * Movimiento 2
        *
        * 264.104 + 50.000 debería producir:
        *
        * 314.104
        *
        * Pero deliberadamente informamos:
        *
        * 314.204
        *
        * Diferencia: +100
        */
        $sheet->setCellValue('A4', '17/Ago');
        $sheet->setCellValue('B4', '9100002');
        $sheet->setCellValue(
            'C4',
            'TRANSFERENCIA RECIBIDA'
        );
        $sheet->setCellValue('D4', 50000);
        $sheet->setCellValue('E4', '');
        $sheet->setCellValue('F4', 314204);

        /*
        * Movimiento 3
        *
        * Continuamos desde el saldo REAL informado
        * por el banco:
        *
        * 314.204 - 10.000 = 304.204
        *
        * Por tanto, NO debe aparecer un segundo
        * falso error.
        */
        $sheet->setCellValue('A5', '18/Ago');
        $sheet->setCellValue('B5', '9100003');
        $sheet->setCellValue(
            'C5',
            'PAGO SERVICIO'
        );
        $sheet->setCellValue('D5', '');
        $sheet->setCellValue('E5', 10000);
        $sheet->setCellValue('F5', 304204);

        $sheet
            ->getStyle('D3:F5')
            ->getNumberFormat()
            ->setFormatCode(
                '#,##0;(#,##0)'
            );

        $writer =
            new Xlsx($spreadsheet);

        $writer->save($file);

        $spreadsheet->disconnectWorksheets();

        unset($spreadsheet);
    }
}
