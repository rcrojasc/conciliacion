<?php

namespace Tests\Feature;

use App\Enums\BankTransactionStatus;
use App\Enums\TaxDocumentDirection;
use App\Enums\TaxPaymentStatus;
use App\Enums\TransactionDirection;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Organization;
use App\Models\TaxDocument;
use App\Services\Tax\TaxDocumentBankMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxDocumentBankMatcherTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private BankAccount $account;
    private TaxDocumentBankMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = app(TaxDocumentBankMatcher::class);

        $this->organization = Organization::create([
            'name' => 'ARIONS TEST',
            'tax_id' => '76123456-7',
            'status' => 'active',
            'base_currency' => 'CLP',
            'timezone' => 'America/Santiago',
        ]);

        $bank = Bank::create([
            'code' => '012',
            'name' => 'BancoEstado Test',
        ]);

        $this->account = BankAccount::create([
            'organization_id' => $this->organization->id,
            'bank_id' => $bank->id,
            'external_id' => 'TEST-001',
            'account_type' => 'cuenta_vista',
            'currency' => 'CLP',
            'masked_number' => '****0001',
            'name' => 'Cuenta Test',
            'status' => 'active',
        ]);
    }

    public function test_sale_finds_credit_with_same_amount(): void
    {
        $document = $this->createSale(
            amount: 50000
        );

        $credit = $this->createTransaction(
            amount: 50000,
            direction: TransactionDirection::Credit
        );

        $candidates = $this->matcher
            ->findCandidates($document);

        $this->assertCount(1, $candidates);

        $this->assertSame(
            $credit->id,
            $candidates->first()['transaction']->id
        );

        $this->assertGreaterThanOrEqual(
            60,
            $candidates->first()['score']
        );

        $this->assertContains(
            'Monto exacto',
            $candidates->first()['reasons']
        );
    }

    public function test_sale_ignores_debit_with_same_amount(): void
    {
        $document = $this->createSale(
            amount: 50000
        );

        $this->createTransaction(
            amount: 50000,
            direction: TransactionDirection::Debit
        );

        $candidates = $this->matcher
            ->findCandidates($document);

        $this->assertCount(0, $candidates);
    }

    public function test_purchase_finds_debit_with_same_amount(): void
    {
        $document = $this->createPurchase(
            amount: 80000
        );

        $debit = $this->createTransaction(
            amount: 80000,
            direction: TransactionDirection::Debit
        );

        $candidates = $this->matcher
            ->findCandidates($document);

        $this->assertCount(1, $candidates);

        $this->assertSame(
            $debit->id,
            $candidates->first()['transaction']->id
        );

        $this->assertGreaterThanOrEqual(
            60,
            $candidates->first()['score']
        );

        $this->assertContains(
            'Monto exacto',
            $candidates->first()['reasons']
        );
    }

    public function test_purchase_ignores_credit_with_same_amount(): void
    {
        $document = $this->createPurchase(
            amount: 80000
        );

        $this->createTransaction(
            amount: 80000,
            direction: TransactionDirection::Credit
        );

        $candidates = $this->matcher
            ->findCandidates($document);

        $this->assertCount(0, $candidates);
    }

    public function test_it_does_not_use_transactions_from_another_organization(): void
    {
        $document = $this->createSale(
            amount: 150000
        );

        $otherOrganization = Organization::create([
            'name' => 'OTRA EMPRESA',
            'tax_id' => '76999999-9',
            'status' => 'active',
            'base_currency' => 'CLP',
            'timezone' => 'America/Santiago',
        ]);

        $otherBank = Bank::create([
            'code' => '999',
            'name' => 'Banco Otra Empresa',
        ]);

        $otherAccount = BankAccount::create([
            'organization_id' => $otherOrganization->id,
            'bank_id' => $otherBank->id,
            'external_id' => 'OTHER-001',
            'account_type' => 'cuenta_vista',
            'currency' => 'CLP',
            'masked_number' => '****9999',
            'name' => 'Cuenta Otra Empresa',
            'status' => 'active',
        ]);

        $this->createTransaction(
            amount: 150000,
            direction: TransactionDirection::Credit,
            organization: $otherOrganization,
            account: $otherAccount
        );

        $candidates = $this->matcher
            ->findCandidates($document);

        $this->assertCount(0, $candidates);
    }

    private function createSale(
        float $amount
    ): TaxDocument {
        return TaxDocument::create([
            'organization_id' => $this->organization->id,

            'direction' =>
                TaxDocumentDirection::SALE,

            'document_type' => 33,
            'folio' => '1001',

            'issuer_tax_id' =>
                $this->organization->tax_id,

            'issuer_name' =>
                $this->organization->name,

            'receiver_tax_id' =>
                '76543210-K',

            'receiver_name' =>
                'CLIENTE TEST SPA',

            'issue_date' =>
                '2026-10-01',

            'due_date' =>
                '2026-10-10',

            'net_amount' =>
                round($amount / 1.19, 4),

            'exempt_amount' => 0,

            'vat_amount' =>
                round(
                    $amount
                    - ($amount / 1.19),
                    4
                ),

            'total_amount' =>
                $amount,

            'currency' => 'CLP',

            'payment_status' =>
                TaxPaymentStatus::PENDING,

            'amount_paid' => 0,

            'amount_outstanding' =>
                $amount,

            'source' => 'test',
        ]);
    }

    private function createPurchase(
        float $amount
    ): TaxDocument {
        return TaxDocument::create([
            'organization_id' => $this->organization->id,

            'direction' =>
                TaxDocumentDirection::PURCHASE,

            'document_type' => 33,
            'folio' => '2001',

            'issuer_tax_id' =>
                '77777777-7',

            'issuer_name' =>
                'PROVEEDOR TEST SPA',

            'receiver_tax_id' =>
                $this->organization->tax_id,

            'receiver_name' =>
                $this->organization->name,

            'issue_date' =>
                '2026-10-01',

            'due_date' =>
                '2026-10-10',

            'net_amount' =>
                round($amount / 1.19, 4),

            'exempt_amount' => 0,

            'vat_amount' =>
                round(
                    $amount
                    - ($amount / 1.19),
                    4
                ),

            'total_amount' =>
                $amount,

            'currency' => 'CLP',

            'payment_status' =>
                TaxPaymentStatus::PENDING,

            'amount_paid' => 0,

            'amount_outstanding' =>
                $amount,

            'source' => 'test',
        ]);
    }

    private function createTransaction(
        float $amount,
        TransactionDirection $direction,
        ?Organization $organization = null,
        ?BankAccount $account = null,
        ?string $counterpartyTaxId = null
    ): BankTransaction {
        $organization ??= $this->organization;
        $account ??= $this->account;

        return BankTransaction::create([
            'organization_id' =>
                $organization->id,

            'bank_account_id' =>
                $account->id,

            'booking_date' =>
                '2026-10-10',

            'value_date' =>
                '2026-10-10',

            'amount' =>
                $amount,

            'currency' =>
                'CLP',

            'direction' =>
                $direction,

            'description_raw' =>
                $direction === TransactionDirection::Credit
                    ? 'TRANSFERENCIA RECIBIDA CLIENTE TEST'
                    : 'TRANSFERENCIA A PROVEEDOR TEST',

            'description_normalized' =>
                $direction === TransactionDirection::Credit
                    ? 'TRANSFERENCIA RECIBIDA CLIENTE TEST'
                    : 'TRANSFERENCIA A PROVEEDOR TEST',

            'reference' =>
                'TEST-' . uniqid(),

            'operation_number' =>
                (string) random_int(
                    1000000,
                    9999999
                ),

            'counterparty_tax_id' =>
                $counterpartyTaxId,

            'balance_after' =>
                500000,

            'fingerprint' =>
                hash(
                    'sha256',
                    uniqid('', true)
                ),

            'status' =>
                BankTransactionStatus::Normalized,
        ]);
    }

    public function test_exact_amount_close_date_and_matching_tax_id_scores_100(): void
    {
        $document = $this->createPurchase(
            amount: 120000
        );

        $transaction = $this->createTransaction(
            amount: 120000,
            direction: TransactionDirection::Debit,
            counterpartyTaxId: '77.777.777-7'
        );

        $candidates = $this->matcher
            ->findCandidates($document);

        $this->assertCount(1, $candidates);

        $candidate = $candidates->first();

        $this->assertSame(
            $transaction->id,
            $candidate['transaction']->id
        );

        $this->assertSame(
            100,
            $candidate['score']
        );

        $this->assertContains(
            'Monto exacto',
            $candidate['reasons']
        );

        $this->assertContains(
            'Fecha muy cercana',
            $candidate['reasons']
        );

        $this->assertContains(
            'RUT de contraparte coincide',
            $candidate['reasons']
        );
    }

    public function test_exact_amount_without_tax_id_scores_80(): void
    {
        $document = $this->createPurchase(
            amount: 120000
        );

        $transaction = $this->createTransaction(
            amount: 120000,
            direction: TransactionDirection::Debit
        );

        $transaction->update([
            'description_raw' => 'PAGO EMPRESA SIN RELACION',
            'description_normalized' => 'PAGO EMPRESA SIN RELACION',
        ]);

        $candidate = $this->matcher
            ->findCandidates($document)
            ->first();

        $this->assertNotNull($candidate);

        $this->assertSame(
            80,
            $candidate['score']
        );

        $this->assertNotContains(
            'RUT de contraparte coincide',
            $candidate['reasons']
        );
    }

    public function test_different_tax_id_does_not_receive_tax_id_points(): void
    {
        $document = $this->createPurchase(
            amount: 120000
        );

        $transaction =
            $this->createTransaction(
                amount: 120000,
                direction: TransactionDirection::Debit,
                counterpartyTaxId: '76.111.111-1'
            );

        $transaction->update([
            'description_raw' =>
                'PAGO EMPRESA SIN RELACION',

            'description_normalized' =>
                'PAGO EMPRESA SIN RELACION',
        ]);

        $candidate = $this->matcher
            ->findCandidates($document)
            ->first();

        $this->assertNotNull($candidate);

        $this->assertSame(
            80,
            $candidate['score']
        );

        $this->assertNotContains(
            'RUT de contraparte coincide',
            $candidate['reasons']
        );
    }

    public function test_candidates_are_sorted_by_score(): void
    {
        $document = $this->createPurchase(
            amount: 120000
        );

        $withoutTaxId = $this->createTransaction(
            amount: 120000,
            direction: TransactionDirection::Debit
        );

        $withTaxId = $this->createTransaction(
            amount: 120000,
            direction: TransactionDirection::Debit,
            counterpartyTaxId: '77.777.777-7'
        );

        $withoutTaxId->update([
            'description_raw' =>
                'PAGO EMPRESA SIN RELACION',

            'description_normalized' =>
                'PAGO EMPRESA SIN RELACION',
        ]);

        $candidates = $this->matcher
            ->findCandidates($document);

        $this->assertCount(2, $candidates);

        $this->assertSame(
            $withTaxId->id,
            $candidates[0]['transaction']->id
        );

        $this->assertSame(
            100,
            $candidates[0]['score']
        );

        $this->assertSame(
            $withoutTaxId->id,
            $candidates[1]['transaction']->id
        );

        $this->assertSame(
            80,
            $candidates[1]['score']
        );
    }
    public function test_purchase_receives_points_when_supplier_name_matches_description(): void
    {
        $document = $this->createPurchase(
            amount: 120000
        );

        $document->update([
            'issuer_name' =>
                'COMERCIAL JUAN PEREZ SPA',
        ]);

        $transaction =
            $this->createTransaction(
                amount: 120000,
                direction: TransactionDirection::Debit
            );

        $transaction->update([
            'description_raw' =>
                'TEF A COMERCIAL JUAN PEREZ SPA',

            'description_normalized' =>
                'TEF A COMERCIAL JUAN PEREZ SPA',
        ]);

        $candidate =
            $this->matcher
                ->findCandidates($document)
                ->first();

        $this->assertNotNull($candidate);

        $this->assertContains(
            'Nombre de contraparte coincide',
            $candidate['reasons']
        );

        $this->assertGreaterThanOrEqual(
            90,
            $candidate['score']
        );
    }

    public function test_sale_receives_points_when_customer_name_matches_description(): void
    {
        $document = $this->createSale(
            amount: 50000
        );

        $document->update([
            'receiver_name' =>
                'TRANSPORTES DEL NORTE LTDA',
        ]);

        $transaction =
            $this->createTransaction(
                amount: 50000,
                direction: TransactionDirection::Credit
            );

        $transaction->update([
            'description_raw' =>
                'TEF DE TRANSPORTES DEL NORTE LTDA',

            'description_normalized' =>
                'TEF DE TRANSPORTES DEL NORTE LTDA',
        ]);

        $candidate =
            $this->matcher
                ->findCandidates($document)
                ->first();

        $this->assertNotNull($candidate);

        $this->assertContains(
            'Nombre de contraparte coincide',
            $candidate['reasons']
        );

        $this->assertGreaterThanOrEqual(
            90,
            $candidate['score']
        );
    }

    public function test_name_comparison_ignores_accents_and_punctuation(): void
    {
        $document = $this->createPurchase(
            amount: 90000
        );

        $document->update([
            'issuer_name' =>
                'SERVICIOS TECNOLÓGICOS ARICA SPA',
        ]);

        $transaction =
            $this->createTransaction(
                amount: 90000,
                direction: TransactionDirection::Debit
            );

        $transaction->update([
            'description_raw' =>
                'TEF A SERVICIOS TECNOLOGICOS ARICA',

            'description_normalized' =>
                'TEF A SERVICIOS TECNOLOGICOS ARICA',
        ]);

        $candidate =
            $this->matcher
                ->findCandidates($document)
                ->first();

        $this->assertNotNull($candidate);

        $this->assertContains(
            'Nombre de contraparte coincide',
            $candidate['reasons']
        );
    }

    public function test_different_counterparty_name_does_not_receive_name_points(): void
    {
        $document = $this->createPurchase(
            amount: 75000
        );

        $document->update([
            'issuer_name' =>
                'EMPRESA ABC SPA',
        ]);

        $transaction =
            $this->createTransaction(
                amount: 75000,
                direction: TransactionDirection::Debit
            );

        $transaction->update([
            'description_raw' =>
                'TEF A EMPRESA XYZ SPA',

            'description_normalized' =>
                'TEF A EMPRESA XYZ SPA',
        ]);

        $candidate =
            $this->matcher
                ->findCandidates($document)
                ->first();

        $this->assertNotNull($candidate);

        $this->assertNotContains(
            'Nombre de contraparte coincide',
            $candidate['reasons']
        );

        $this->assertNotContains(
            'Nombre de contraparte similar',
            $candidate['reasons']
        );
    }
}
