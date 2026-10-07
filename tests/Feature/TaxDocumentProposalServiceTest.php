<?php

namespace Tests\Feature;

use App\Enums\BankTransactionStatus;
use App\Enums\TaxDocumentDirection;
use App\Enums\TaxDocumentPaymentStatus;
use App\Enums\TaxPaymentStatus;
use App\Enums\TransactionDirection;
use App\Enums\TaxDocumentMatchMethod;
use App\Services\Tax\TaxDocumentPaymentService;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Organization;
use App\Models\TaxDocument;
use App\Models\TaxDocumentPayment;
use App\Services\Tax\TaxDocumentProposalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;



class TaxDocumentProposalServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private BankAccount $account;
    private TaxDocumentProposalService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service =
            app(TaxDocumentProposalService::class);

        $this->organization =
            Organization::create([
                'name' => 'ARIONS TEST',
                'tax_id' => '76123456-7',
                'status' => 'active',
                'base_currency' => 'CLP',
                'timezone' => 'America/Santiago',
            ]);

        $bank =
            Bank::create([
                'code' => '012',
                'name' => 'BancoEstado Test',
            ]);

        $this->account =
            BankAccount::create([
                'organization_id' => $this->organization->id,
                'bank_id' => $bank->id,
                'external_id' => 'PROPOSAL-TEST-001',
                'account_type' => 'cuenta_vista',
                'currency' => 'CLP',
                'masked_number' => '****0001',
                'name' => 'Cuenta Test',
                'status' => 'active',
            ]);
    }

    public function test_creates_proposal_for_unique_high_confidence_candidate(): void
    {
        $document =
            $this->createPurchase(
                amount: 100000
            );

        $transaction =
            $this->createTransaction(
                amount: 100000,
                taxId: '77777777-7',
                description: 'TRANSFERENCIA A PROVEEDOR TEST SPA'
            );

        $result =
            $this->service->generate(
                $document
            );

        $this->assertSame(
            'proposed',
            $result['status']
        );

        $this->assertNotNull(
            $result['proposal']
        );

        $proposal =
            $result['proposal'];

        $this->assertSame(
            TaxDocumentPaymentStatus::PROPOSED,
            $proposal->status
        );

        $this->assertSame(
            $document->id,
            $proposal->tax_document_id
        );

        $this->assertSame(
            $transaction->id,
            $proposal->bank_transaction_id
        );

        $this->assertSame(
            '100000.0000',
            $proposal->amount_applied
        );

        $this->assertGreaterThanOrEqual(
            90,
            (float) $proposal->match_score
        );

        /*
         * Fundamental:
         * generar la propuesta NO paga la factura.
         */
        $document->refresh();

        $this->assertSame(
            TaxPaymentStatus::PENDING,
            $document->payment_status
        );

        $this->assertSame(
            '0.0000',
            $document->amount_paid
        );

        $this->assertSame(
            '100000.0000',
            $document->amount_outstanding
        );
    }

    public function test_does_not_create_proposal_when_candidate_score_is_too_low(): void
    {
        $document =
            $this->createPurchase(
                amount: 100000
            );

        /*
         * Monto distinto, RUT distinto y nombre
         * sin relación suficiente.
         *
         * La fecha todavía puede entregar algunos
         * puntos, pero no debería alcanzar 90.
         */
        $this->createTransaction(
            amount: 70000,
            taxId: '11111111-1',
            description: 'PAGO EMPRESA SIN RELACION'
        );

        $result =
            $this->service->generate(
                $document
            );

        $this->assertContains(
            $result['status'],
            [
                'low_confidence',
                'no_candidates',
            ]
        );

        $this->assertDatabaseCount(
            'tax_document_payments',
            0
        );
    }

    public function test_does_not_choose_automatically_between_ambiguous_candidates(): void
    {
        $document =
            $this->createPurchase(
                amount: 100000
            );

        $this->createTransaction(
            amount: 100000,
            taxId: '77777777-7',
            description: 'TRANSFERENCIA A PROVEEDOR TEST SPA'
        );

        $this->createTransaction(
            amount: 100000,
            taxId: '77777777-7',
            description: 'PAGO PROVEEDOR TEST SPA'
        );

        $result =
            $this->service->generate(
                $document
            );

        $this->assertSame(
            'ambiguous',
            $result['status']
        );

        $this->assertGreaterThanOrEqual(
            2,
            $result['candidates_count']
        );

        $this->assertDatabaseCount(
            'tax_document_payments',
            0
        );
    }

    public function test_does_not_create_second_proposal_when_pending_proposal_exists(): void
    {
        $document =
            $this->createPurchase(
                amount: 100000
            );

        $this->createTransaction(
            amount: 100000,
            taxId: '77777777-7',
            description: 'TRANSFERENCIA A PROVEEDOR TEST SPA'
        );

        $first =
            $this->service->generate(
                $document
            );

        $this->assertSame(
            'proposed',
            $first['status']
        );

        $second =
            $this->service->generate(
                $document
            );

        $this->assertSame(
            'existing_proposal',
            $second['status']
        );

        $this->assertNotNull(
            $second['proposal']
        );

        $this->assertSame(
            $first['proposal']->id,
            $second['proposal']->id
        );

        $this->assertDatabaseCount(
            'tax_document_payments',
            1
        );
    }

    public function test_paid_document_does_not_generate_new_proposal(): void
    {
        $document =
            $this->createPurchase(
                amount: 100000,
                paymentStatus: TaxPaymentStatus::PAID,
                amountPaid: 100000,
                outstanding: 0
            );

        $this->createTransaction(
            amount: 100000,
            taxId: '77777777-7',
            description: 'TRANSFERENCIA A PROVEEDOR TEST SPA'
        );

        $result =
            $this->service->generate(
                $document
            );

        $this->assertSame(
            'already_paid',
            $result['status']
        );

        $this->assertDatabaseCount(
            'tax_document_payments',
            0
        );
    }

    public function test_cancelled_document_does_not_generate_proposal(): void
    {
        $document =
            $this->createPurchase(
                amount: 100000,
                paymentStatus: TaxPaymentStatus::CANCELLED,
                amountPaid: 0,
                outstanding: 100000
            );

        $this->createTransaction(
            amount: 100000,
            taxId: '77777777-7',
            description: 'TRANSFERENCIA A PROVEEDOR TEST SPA'
        );

        $result =
            $this->service->generate(
                $document
            );

        $this->assertSame(
            'cancelled',
            $result['status']
        );

        $this->assertDatabaseCount(
            'tax_document_payments',
            0
        );
    }

    public function test_partial_document_proposes_only_outstanding_balance(): void
    {
        $document =
            $this->createPurchase(
                amount: 100000,
                paymentStatus: TaxPaymentStatus::PARTIAL,
                amountPaid: 40000,
                outstanding: 60000
            );

        /*
         * Usamos un movimiento por $100.000 para comprobar
         * que el ProposalService nunca propone más que
         * el saldo pendiente de la factura.
         */
        $this->createTransaction(
            amount: 100000,
            taxId: '77777777-7',
            description: 'TRANSFERENCIA A PROVEEDOR TEST SPA'
        );

        $result =
            $this->service->generate(
                $document
            );

        $this->assertSame(
            'proposed',
            $result['status']
        );

        $this->assertSame(
            '60000.0000',
            $result['proposal']->amount_applied
        );

        /*
         * Sigue siendo parcial porque la propuesta
         * todavía no está aprobada.
         */
        $document->refresh();

        $this->assertSame(
            TaxPaymentStatus::PARTIAL,
            $document->payment_status
        );

        $this->assertSame(
            '40000.0000',
            $document->amount_paid
        );

        $this->assertSame(
            '60000.0000',
            $document->amount_outstanding
        );
    }

    public function test_candidate_from_other_organization_is_not_used(): void
    {
        $document =
            $this->createPurchase(
                amount: 100000
            );

        $otherOrganization =
            Organization::create([
                'name' => 'OTRA EMPRESA',
                'tax_id' => '76999999-9',
                'status' => 'active',
                'base_currency' => 'CLP',
                'timezone' => 'America/Santiago',
            ]);

        $otherBank =
            Bank::create([
                'code' => '999',
                'name' => 'Banco Otra Empresa',
            ]);

        $otherAccount =
            BankAccount::create([
                'organization_id' =>
                    $otherOrganization->id,

                'bank_id' =>
                    $otherBank->id,

                'external_id' =>
                    'OTHER-PROPOSAL-001',

                'account_type' =>
                    'cuenta_vista',

                'currency' =>
                    'CLP',

                'masked_number' =>
                    '****9999',

                'name' =>
                    'Cuenta Otra Empresa',

                'status' =>
                    'active',
            ]);

        $this->createTransaction(
            amount: 100000,
            taxId: '77777777-7',
            description: 'TRANSFERENCIA A PROVEEDOR TEST SPA',
            organization: $otherOrganization,
            account: $otherAccount
        );

        $result =
            $this->service->generate(
                $document
            );

        $this->assertSame(
            'no_candidates',
            $result['status']
        );

        $this->assertDatabaseCount(
            'tax_document_payments',
            0
        );
    }

    public function test_proposal_stores_match_evidence_in_metadata(): void
    {
        $document =
            $this->createPurchase(
                amount: 100000
            );

        $this->createTransaction(
            amount: 100000,
            taxId: '77777777-7',
            description: 'TRANSFERENCIA A PROVEEDOR TEST SPA'
        );

        $result =
            $this->service->generate(
                $document
            );

        $this->assertSame(
            'proposed',
            $result['status']
        );

        /** @var TaxDocumentPayment $proposal */
        $proposal =
            $result['proposal'];

        $metadata =
            $proposal->metadata;

        $this->assertIsArray(
            $metadata
        );

        $this->assertArrayHasKey(
            'proposal_engine',
            $metadata
        );

        $engine =
            $metadata['proposal_engine'];

        $this->assertSame(
            2,
            $engine['version']
        );

        $this->assertTrue(
            $engine['automatic']
        );

        $this->assertSame(
            90.0,
            (float) $engine['threshold']
        );

        $this->assertSame(
            5.0,
            (float) $engine['ambiguity_margin']
        );

        $this->assertGreaterThanOrEqual(
            90,
            (float) $engine['score']
        );

        $this->assertIsArray(
            $engine['reasons']
        );

        $this->assertNotEmpty(
            $engine['reasons']
        );

        $this->assertNotEmpty(
            $engine['generated_at']
        );
    }

    public function test_proposal_uses_only_available_transaction_balance(): void
    {
        $firstDocument =
            $this->createPurchase(70000);

        $targetDocument =
            $this->createPurchase(100000);

        $transaction =
            $this->createTransaction(
                amount: 100000,
                taxId: '77777777-7',
                description: 'TRANSFERENCIA A PROVEEDOR TEST SPA'
            );

        $paymentService =
            app(TaxDocumentPaymentService::class);

        $existingPayment =
            $paymentService->propose(
                document: $firstDocument,
                transaction: $transaction,
                amount: 70000,
                method: TaxDocumentMatchMethod::MANUAL
            );

        $paymentService->approve(
            $existingPayment
        );

        $result =
            $this->service->generate(
                $targetDocument
            );

        $this->assertSame(
            'proposed',
            $result['status']
        );

        $this->assertSame(
            '30000.0000',
            $result['proposal']->amount_applied
        );
    }

    public function test_fully_applied_transaction_does_not_generate_proposal(): void
    {
        $firstDocument =
            $this->createPurchase(100000);

        $targetDocument =
            $this->createPurchase(100000);

        $transaction =
            $this->createTransaction(
                amount: 100000,
                taxId: '77777777-7',
                description: 'TRANSFERENCIA A PROVEEDOR TEST SPA'
            );

        $paymentService =
            app(TaxDocumentPaymentService::class);

        $existingPayment =
            $paymentService->propose(
                document: $firstDocument,
                transaction: $transaction,
                amount: 100000,
                method: TaxDocumentMatchMethod::MANUAL
            );

        $paymentService->approve(
            $existingPayment
        );

        $result =
            $this->service->generate(
                $targetDocument
            );

        $this->assertSame(
            'transaction_fully_applied',
            $result['status']
        );

        /*
        * Existe solamente la aplicación aprobada
        * del primer documento.
        */
        $this->assertDatabaseCount(
            'tax_document_payments',
            1
        );
    }

    public function test_reversed_payment_releases_balance_for_new_proposal(): void
    {
        $firstDocument =
            $this->createPurchase(100000);

        $targetDocument =
            $this->createPurchase(100000);

        $transaction =
            $this->createTransaction(
                amount: 100000,
                taxId: '77777777-7',
                description: 'TRANSFERENCIA A PROVEEDOR TEST SPA'
            );

        $paymentService =
            app(TaxDocumentPaymentService::class);

        $existingPayment =
            $paymentService->propose(
                document: $firstDocument,
                transaction: $transaction,
                amount: 100000,
                method: TaxDocumentMatchMethod::MANUAL
            );

        $existingPayment =
            $paymentService->approve(
                $existingPayment
            );

        $paymentService->reverse(
            $existingPayment
        );

        $result =
            $this->service->generate(
                $targetDocument
            );

        $this->assertSame(
            'proposed',
            $result['status']
        );

        $this->assertSame(
            '100000.0000',
            $result['proposal']->amount_applied
        );

        $this->assertSame(
            TaxDocumentPaymentStatus::PROPOSED,
            $result['proposal']->status
        );
    }

    public function test_proposal_metadata_records_transaction_availability(): void
    {
        $firstDocument =
            $this->createPurchase(40000);

        $targetDocument =
            $this->createPurchase(100000);

        $transaction =
            $this->createTransaction(
                amount: 100000,
                taxId: '77777777-7',
                description: 'TRANSFERENCIA A PROVEEDOR TEST SPA'
            );

        $paymentService =
            app(TaxDocumentPaymentService::class);

        $existingPayment =
            $paymentService->propose(
                document: $firstDocument,
                transaction: $transaction,
                amount: 40000,
                method: TaxDocumentMatchMethod::MANUAL
            );

        $paymentService->approve(
            $existingPayment
        );

        $result =
            $this->service->generate(
                $targetDocument
            );

        $this->assertSame(
            'proposed',
            $result['status']
        );

        $proposal =
            $result['proposal'];

        $engine =
            $proposal->metadata[
                'proposal_engine'
            ];

        $this->assertSame(
            2,
            $engine['version']
        );

        $this->assertSame(
            100000.0,
            (float) $engine['transaction_amount']
        );

        $this->assertSame(
            40000.0,
            (float) $engine['transaction_approved_amount']
        );

        $this->assertSame(
            60000.0,
            (float) $engine['transaction_available_amount']
        );

        $this->assertSame(
            60000.0,
            (float) $engine['proposed_amount']
        );
    }

    private function createPurchase(
        float $amount,
        TaxPaymentStatus $paymentStatus =
            TaxPaymentStatus::PENDING,
        float $amountPaid = 0,
        ?float $outstanding = null
    ): TaxDocument {
        $outstanding ??=
            max(
                $amount - $amountPaid,
                0
            );

        return TaxDocument::create([
            'organization_id' =>
                $this->organization->id,

            'direction' =>
                TaxDocumentDirection::PURCHASE,

            'document_type' =>
                33,

            'folio' =>
                'PROP-' . uniqid(),

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
                round(
                    $amount / 1.19,
                    4
                ),

            'exempt_amount' =>
                0,

            'vat_amount' =>
                round(
                    $amount
                    - ($amount / 1.19),
                    4
                ),

            'total_amount' =>
                $amount,

            'currency' =>
                'CLP',

            'payment_status' =>
                $paymentStatus,

            'amount_paid' =>
                $amountPaid,

            'amount_outstanding' =>
                $outstanding,

            'source' =>
                'test',
        ]);
    }

    private function createTransaction(
        float $amount,
        ?string $taxId = null,
        string $description =
            'TRANSFERENCIA A PROVEEDOR TEST SPA',
        ?Organization $organization = null,
        ?BankAccount $account = null
    ): BankTransaction {
        $organization ??=
            $this->organization;

        $account ??=
            $this->account;

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
                TransactionDirection::Debit,

            'description_raw' =>
                $description,

            'description_normalized' =>
                $description,

            'reference' =>
                'PROP-' . uniqid(),

            'operation_number' =>
                (string) random_int(
                    1000000,
                    9999999
                ),

            'counterparty_tax_id' =>
                $taxId,

            'counterparty_name' =>
                null,

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
}
