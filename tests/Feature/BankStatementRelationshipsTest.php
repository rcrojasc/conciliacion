<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\BankTransaction;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankStatementRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_statement_can_have_transactions_through_pivot(): void
    {
        [$organization, $account] = $this->createBankContext();

        $statement = BankStatement::query()->create([
            'organization_id' => $organization->id,
            'bank_account_id' => $account->id,
            'period_from' => '2026-08-17',
            'period_to' => '2026-08-26',
            'currency' => 'CLP',
            'status' => 'processed',
        ]);

        $transaction = BankTransaction::query()->create([
            'organization_id' => $organization->id,
            'bank_account_id' => $account->id,
            'booking_date' => '2026-08-17',
            'amount' => 470,
            'currency' => 'CLP',
            'direction' => 'debit',
            'description_raw' => 'COMISION TRANSACCION INTERNACIONAL',
            'description_normalized' => 'COMISION TRANSACCION INTERNACIONAL',
            'operation_number' => '8083883',
            'balance_after' => 264104,
            'fingerprint' => hash('sha256', 'phase-c-test'),
            'status' => 'normalized',
        ]);

        BankStatementTransaction::query()->create([
            'organization_id' => $organization->id,
            'bank_statement_id' => $statement->id,
            'bank_transaction_id' => $transaction->id,
            'position' => 1,
            'source_row' => 20,
            'balance_after_reported' => 264104,
        ]);

        $statement->refresh();
        $transaction->refresh();

        $this->assertCount(
            1,
            $statement->transactions
        );

        $this->assertSame(
            $transaction->id,
            $statement->transactions->first()->id
        );

        $this->assertSame(
            1,
            $statement->transactions->first()->pivot->position
        );

        $this->assertSame(
            20,
            $statement->transactions->first()->pivot->source_row
        );

       $this->assertEquals(
            264104.0,
            (float) $statement->transactions
                ->first()
                ->pivot
                ->balance_after_reported
        );
        $this->assertCount(
            1,
            $transaction->statements
        );

        $this->assertSame(
            $statement->id,
            $transaction->statements->first()->id
        );
    }

    public function test_same_transaction_can_belong_to_multiple_statements(): void
    {
        [$organization, $account] = $this->createBankContext();

        $statementA = BankStatement::query()->create([
            'organization_id' => $organization->id,
            'bank_account_id' => $account->id,
            'period_from' => '2026-08-17',
            'period_to' => '2026-08-26',
            'currency' => 'CLP',
            'status' => 'processed',
        ]);

        $statementB = BankStatement::query()->create([
            'organization_id' => $organization->id,
            'bank_account_id' => $account->id,
            'period_from' => '2026-08-20',
            'period_to' => '2026-08-31',
            'currency' => 'CLP',
            'status' => 'processed',
        ]);

        $transaction = BankTransaction::query()->create([
            'organization_id' => $organization->id,
            'bank_account_id' => $account->id,
            'booking_date' => '2026-08-20',
            'amount' => 12990,
            'currency' => 'CLP',
            'direction' => 'debit',
            'description_raw' => 'PAGO NETFLIX',
            'description_normalized' => 'PAGO NETFLIX',
            'operation_number' => '8074975',
            'balance_after' => 272655,
            'fingerprint' => hash('sha256', 'phase-c-overlap'),
            'status' => 'normalized',
        ]);

        BankStatementTransaction::query()->create([
            'organization_id' => $organization->id,
            'bank_statement_id' => $statementA->id,
            'bank_transaction_id' => $transaction->id,
            'position' => 1,
            'balance_after_reported' => 272655,
        ]);

        BankStatementTransaction::query()->create([
            'organization_id' => $organization->id,
            'bank_statement_id' => $statementB->id,
            'bank_transaction_id' => $transaction->id,
            'position' => 1,
            'balance_after_reported' => 272655,
        ]);

        $transaction->refresh();

        $this->assertCount(
            2,
            $transaction->statements
        );

        $this->assertTrue(
            $transaction->statements->contains(
                'id',
                $statementA->id
            )
        );

        $this->assertTrue(
            $transaction->statements->contains(
                'id',
                $statementB->id
            )
        );

        /*
         * Sigue existiendo una sola transacción bancaria.
         * Lo que existen son dos apariciones documentales.
         */
        $this->assertSame(
            1,
            BankTransaction::query()->count()
        );

        $this->assertSame(
            2,
            BankStatementTransaction::query()->count()
        );
    }

    private function createBankContext(): array
    {
        $organization = Organization::query()->create([
            'name' => 'ARIONS TEST '.uniqid(),
            'status' => 'active',
            'base_currency' => 'CLP',
            'timezone' => 'America/Santiago',
        ]);

        $bank = Bank::query()->create([
            'code' => '012-'.uniqid(),
            'name' => 'BancoEstado Test',
            'country' => 'CL',
            'status' => 'active',
        ]);

        $account = BankAccount::query()->create([
            'organization_id' => $organization->id,
            'bank_id' => $bank->id,
            'external_id' => 'TEST-'.uniqid(),
            'name' => 'Cuenta Test',
            'account_type' => 'cuenta_vista',
            'currency' => 'CLP',
            'masked_number' => '****0001',
            'status' => 'active',
        ]);

        return [
            $organization,
            $account,
        ];
    }
}
