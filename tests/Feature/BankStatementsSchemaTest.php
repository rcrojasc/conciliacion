<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BankStatementsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_statements_has_phase_b_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'bank_statements',
                [
                    'bank_import_id',
                    'status',
                    'statement_reference',
                    'metadata',
                ]
            )
        );
    }

    public function test_bank_statements_preserves_existing_financial_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'bank_statements',
                [
                    'id',
                    'organization_id',
                    'bank_account_id',
                    'period_from',
                    'period_to',
                    'opening_balance',
                    'closing_balance',
                    'currency',
                    'created_at',
                    'updated_at',
                ]
            )
        );
    }

    public function test_legacy_statement_relationship_is_preserved_during_transition(): void
    {
        $this->assertTrue(
            Schema::hasColumn(
                'bank_transactions',
                'statement_id'
            )
        );
    }

    public function test_statement_transaction_pivot_is_preserved(): void
    {
        $this->assertTrue(
            Schema::hasTable(
                'bank_statement_transactions'
            )
        );
    }
}
