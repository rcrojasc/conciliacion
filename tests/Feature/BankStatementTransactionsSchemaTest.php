<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BankStatementTransactionsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_statement_transactions_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable(
                'bank_statement_transactions'
            )
        );
    }

    public function test_bank_statement_transactions_has_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'bank_statement_transactions',
                [
                    'id',
                    'organization_id',
                    'bank_statement_id',
                    'bank_transaction_id',
                    'position',
                    'source_row',
                    'balance_after_reported',
                    'created_at',
                    'updated_at',
                ]
            )
        );
    }

    public function test_legacy_statement_id_is_still_preserved(): void
    {
        /*
         * Durante la transición NO eliminaremos todavía
         * bank_transactions.statement_id.
         */
        $this->assertTrue(
            Schema::hasColumn(
                'bank_transactions',
                'statement_id'
            )
        );
    }

    public function test_bank_import_id_is_still_preserved(): void
    {
        /*
         * bank_import_id conserva la procedencia:
         * qué importación creó originalmente el movimiento.
         */
        $this->assertTrue(
            Schema::hasColumn(
                'bank_transactions',
                'bank_import_id'
            )
        );
    }
}
