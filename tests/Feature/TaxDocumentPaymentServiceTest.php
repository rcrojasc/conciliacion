<?php

namespace Tests\Feature;

use App\Enums\BankTransactionStatus;
use App\Enums\TaxDocumentDirection;
use App\Enums\TaxDocumentMatchMethod;
use App\Enums\TaxDocumentPaymentStatus;
use App\Enums\TaxPaymentStatus;
use App\Enums\TransactionDirection;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Organization;
use App\Models\TaxDocument;
use App\Services\Tax\TaxDocumentPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class TaxDocumentPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private BankAccount $account;
    private TaxDocumentPaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service =
            app(TaxDocumentPaymentService::class);

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
                'organization_id' =>
                    $this->organization->id,

                'bank_id' =>
                    $bank->id,

                'external_id' =>
                    'PAYMENT-TEST-001',

                'account_type' =>
                    'cuenta_vista',

                'currency' =>
                    'CLP',

                'masked_number' =>
                    '****0001',

                'name' =>
                    'Cuenta Test',

                'status' =>
                    'active',
            ]);
    }

    public function test_proposal_does_not_change_document_balance(): void
    {
        $document =
            $this->createDocument(100000);

        $transaction =
            $this->createTransaction(100000);

        $payment =
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: 100000,
                method: TaxDocumentMatchMethod::EXACT_AMOUNT,
                score: 100
            );

        $document->refresh();

        $this->assertSame(
            TaxDocumentPaymentStatus::PROPOSED,
            $payment->status
        );

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

    public function test_approving_partial_payment_marks_document_as_partial(): void
    {
        $document =
            $this->createDocument(100000);

        $transaction =
            $this->createTransaction(40000);

        $payment =
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: 40000,
                method: TaxDocumentMatchMethod::EXACT_AMOUNT,
                score: 95
            );

        $payment =
            $this->service->approve($payment);

        $document->refresh();

        $this->assertSame(
            TaxDocumentPaymentStatus::APPROVED,
            $payment->status
        );

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

        $this->assertNotNull(
            $payment->approved_at
        );
    }

    public function test_approving_full_payment_marks_document_as_paid(): void
    {
        $document =
            $this->createDocument(100000);

        $transaction =
            $this->createTransaction(100000);

        $payment =
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: 100000,
                method: TaxDocumentMatchMethod::EXACT_AMOUNT,
                score: 100
            );

        $this->service->approve($payment);

        $document->refresh();

        $this->assertSame(
            TaxPaymentStatus::PAID,
            $document->payment_status
        );

        $this->assertSame(
            '100000.0000',
            $document->amount_paid
        );

        $this->assertSame(
            '0.0000',
            $document->amount_outstanding
        );
    }

    public function test_approving_excess_payment_marks_document_as_overpaid(): void
    {
        $document =
            $this->createDocument(100000);

        $transaction =
            $this->createTransaction(110000);

        $payment =
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: 110000,
                method: TaxDocumentMatchMethod::MANUAL
            );

        $this->service->approve($payment);

        $document->refresh();

        $this->assertSame(
            TaxPaymentStatus::OVERPAID,
            $document->payment_status
        );

        $this->assertSame(
            '110000.0000',
            $document->amount_paid
        );

        $this->assertSame(
            '0.0000',
            $document->amount_outstanding
        );
    }

    public function test_two_approved_payments_can_fully_pay_document(): void
    {
        $document =
            $this->createDocument(100000);

        $transactionOne =
            $this->createTransaction(40000);

        $transactionTwo =
            $this->createTransaction(60000);

        $paymentOne =
            $this->service->propose(
                document: $document,
                transaction: $transactionOne,
                amount: 40000,
                method: TaxDocumentMatchMethod::EXACT_AMOUNT
            );

        $this->service->approve($paymentOne);

        $document->refresh();

        $this->assertSame(
            TaxPaymentStatus::PARTIAL,
            $document->payment_status
        );

        $paymentTwo =
            $this->service->propose(
                document: $document,
                transaction: $transactionTwo,
                amount: 60000,
                method: TaxDocumentMatchMethod::EXACT_AMOUNT
            );

        $this->service->approve($paymentTwo);

        $document->refresh();

        $this->assertSame(
            TaxPaymentStatus::PAID,
            $document->payment_status
        );

        $this->assertSame(
            '100000.0000',
            $document->amount_paid
        );

        $this->assertSame(
            '0.0000',
            $document->amount_outstanding
        );

        $this->assertCount(
            2,
            $document->payments
        );
    }

    public function test_rejected_proposal_does_not_change_document_balance(): void
    {
        $document =
            $this->createDocument(100000);

        $transaction =
            $this->createTransaction(100000);

        $payment =
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: 100000,
                method: TaxDocumentMatchMethod::MANUAL
            );

        $payment =
            $this->service->reject($payment);

        $document->refresh();

        $this->assertSame(
            TaxDocumentPaymentStatus::REJECTED,
            $payment->status
        );

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

    public function test_reversing_approved_payment_recalculates_document(): void
    {
        $document =
            $this->createDocument(100000);

        $transactionOne =
            $this->createTransaction(40000);

        $transactionTwo =
            $this->createTransaction(60000);

        $paymentOne =
            $this->service->propose(
                document: $document,
                transaction: $transactionOne,
                amount: 40000,
                method: TaxDocumentMatchMethod::MANUAL
            );

        $paymentTwo =
            $this->service->propose(
                document: $document,
                transaction: $transactionTwo,
                amount: 60000,
                method: TaxDocumentMatchMethod::MANUAL
            );

        $this->service->approve($paymentOne);
        $this->service->approve($paymentTwo);

        $document->refresh();

        $this->assertSame(
            TaxPaymentStatus::PAID,
            $document->payment_status
        );

        $paymentTwo =
            $this->service->reverse($paymentTwo);

        $document->refresh();

        $this->assertSame(
            TaxDocumentPaymentStatus::REVERSED,
            $paymentTwo->status
        );

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

    public function test_document_and_transaction_must_belong_to_same_organization(): void
    {
        $document =
            $this->createDocument(100000);

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
                    'OTHER-PAYMENT-001',

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

        $transaction =
            $this->createTransaction(
                amount: 100000,
                organization: $otherOrganization,
                account: $otherAccount
            );

        try {
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: 100000,
                method: TaxDocumentMatchMethod::MANUAL
            );

            $this->fail(
                'Se esperaba una InvalidArgumentException.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'El documento tributario y el movimiento bancario pertenecen a organizaciones diferentes.',
                $exception->getMessage()
            );
        }
    }

    public function test_zero_amount_cannot_be_proposed(): void
    {
        $document =
            $this->createDocument(100000);

        $transaction =
            $this->createTransaction(100000);

        try {
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: 0,
                method: TaxDocumentMatchMethod::MANUAL
            );

            $this->fail(
                'Se esperaba una InvalidArgumentException.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'El monto aplicado debe ser mayor que cero.',
                $exception->getMessage()
            );
        }
    }

    public function test_negative_amount_cannot_be_proposed(): void
    {
        $document =
            $this->createDocument(100000);

        $transaction =
            $this->createTransaction(100000);

        try {
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: -50000,
                method: TaxDocumentMatchMethod::MANUAL
            );

            $this->fail(
                'Se esperaba una InvalidArgumentException.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'El monto aplicado debe ser mayor que cero.',
                $exception->getMessage()
            );
        }
    }

    public function test_same_payment_cannot_be_approved_twice(): void
    {
        $document =
            $this->createDocument(100000);

        $transaction =
            $this->createTransaction(100000);

        $payment =
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: 100000,
                method: TaxDocumentMatchMethod::EXACT_AMOUNT
            );

        $payment =
            $this->service->approve($payment);

        try {
            $this->service->approve($payment);

            $this->fail(
                'Se esperaba una RuntimeException.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Solo una aplicación propuesta puede ser aprobada.',
                $exception->getMessage()
            );
        }
    }

    public function test_bank_transaction_can_be_split_between_two_documents(): void
    {
        $documentOne =
            $this->createDocument(40000);

        $documentTwo =
            $this->createDocument(60000);

        $transaction =
            $this->createTransaction(100000);

        $paymentOne =
            $this->service->propose(
                document: $documentOne,
                transaction: $transaction,
                amount: 40000,
                method: TaxDocumentMatchMethod::COMBINATION
            );

        $paymentTwo =
            $this->service->propose(
                document: $documentTwo,
                transaction: $transaction,
                amount: 60000,
                method: TaxDocumentMatchMethod::COMBINATION
            );

        $this->service->approve($paymentOne);
        $this->service->approve($paymentTwo);

        $documentOne->refresh();
        $documentTwo->refresh();

        $this->assertSame(
            TaxPaymentStatus::PAID,
            $documentOne->payment_status
        );

        $this->assertSame(
            TaxPaymentStatus::PAID,
            $documentTwo->payment_status
        );

        $this->assertSame(
            '40000.0000',
            $documentOne->amount_paid
        );

        $this->assertSame(
            '60000.0000',
            $documentTwo->amount_paid
        );
    }

    public function test_bank_transaction_cannot_be_overapplied_between_documents(): void
    {
        $documentOne =
            $this->createDocument(80000);

        $documentTwo =
            $this->createDocument(50000);

        $transaction =
            $this->createTransaction(100000);

        $paymentOne =
            $this->service->propose(
                document: $documentOne,
                transaction: $transaction,
                amount: 80000,
                method: TaxDocumentMatchMethod::COMBINATION
            );

        $paymentTwo =
            $this->service->propose(
                document: $documentTwo,
                transaction: $transaction,
                amount: 50000,
                method: TaxDocumentMatchMethod::COMBINATION
            );

        $this->service->approve($paymentOne);

        try {
            $this->service->approve($paymentTwo);

            $this->fail(
                'La sobreaplicación debió ser bloqueada.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'El monto aplicado supera el saldo disponible del movimiento bancario.',
                $exception->getMessage()
            );
        }
    }

    public function test_reversing_payment_releases_bank_transaction_amount(): void
    {
        $documentOne =
            $this->createDocument(80000);

        $documentTwo =
            $this->createDocument(80000);

        $transaction =
            $this->createTransaction(100000);

        $paymentOne =
            $this->service->propose(
                document: $documentOne,
                transaction: $transaction,
                amount: 80000,
                method: TaxDocumentMatchMethod::COMBINATION
            );

        $paymentTwo =
            $this->service->propose(
                document: $documentTwo,
                transaction: $transaction,
                amount: 80000,
                method: TaxDocumentMatchMethod::COMBINATION
            );

        $paymentOne =
            $this->service->approve($paymentOne);

        $this->service->reverse($paymentOne);

        $paymentTwo =
            $this->service->approve($paymentTwo);

        $documentOne->refresh();
        $documentTwo->refresh();

        $this->assertSame(
            TaxPaymentStatus::PENDING,
            $documentOne->payment_status
        );

        $this->assertSame(
            '0.0000',
            $documentOne->amount_paid
        );

        $this->assertSame(
            '80000.0000',
            $documentOne->amount_outstanding
        );

        $this->assertSame(
            TaxDocumentPaymentStatus::APPROVED,
            $paymentTwo->status
        );

        $this->assertSame(
            TaxPaymentStatus::PAID,
            $documentTwo->payment_status
        );

        $this->assertSame(
            '80000.0000',
            $documentTwo->amount_paid
        );
    }

    public function test_failed_overapplication_keeps_second_payment_as_proposed(): void
    {
        $documentOne =
            $this->createDocument(80000);

        $documentTwo =
            $this->createDocument(50000);

        $transaction =
            $this->createTransaction(100000);

        $paymentOne =
            $this->service->propose(
                document: $documentOne,
                transaction: $transaction,
                amount: 80000,
                method: TaxDocumentMatchMethod::COMBINATION
            );

        $paymentTwo =
            $this->service->propose(
                document: $documentTwo,
                transaction: $transaction,
                amount: 50000,
                method: TaxDocumentMatchMethod::COMBINATION
            );

        $this->service->approve($paymentOne);

        try {
            $this->service->approve($paymentTwo);

            $this->fail(
                'La sobreaplicación debió ser bloqueada.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'El monto aplicado supera el saldo disponible del movimiento bancario.',
                $exception->getMessage()
            );
        }

        $paymentTwo->refresh();
        $documentTwo->refresh();

        $this->assertSame(
            TaxDocumentPaymentStatus::PROPOSED,
            $paymentTwo->status
        );

        $this->assertSame(
            TaxPaymentStatus::PENDING,
            $documentTwo->payment_status
        );

        $this->assertSame(
            '0.0000',
            $documentTwo->amount_paid
        );

        $this->assertSame(
            '50000.0000',
            $documentTwo->amount_outstanding
        );
    }

    public function test_purchase_cannot_be_proposed_against_credit_transaction(): void
    {
        $document =
            $this->createDocument(100000);

        $transaction =
            $this->createTransaction(100000);

        $transaction->update([
            'direction' =>
                TransactionDirection::Credit,
        ]);

        $transaction->refresh();

        try {
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: 100000,
                method: TaxDocumentMatchMethod::MANUAL
            );

            $this->fail(
                'Se esperaba una InvalidArgumentException.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Una factura de compra solo puede asociarse a un cargo o débito bancario.',
                $exception->getMessage()
            );
        }
    }

    public function test_purchase_accepts_debit_transaction(): void
    {
        $document =
            $this->createDocument(100000);

        $transaction =
            $this->createTransaction(100000);

        $payment =
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: 100000,
                method: TaxDocumentMatchMethod::MANUAL
            );

        $this->assertSame(
            TaxDocumentPaymentStatus::PROPOSED,
            $payment->status
        );

        $this->assertSame(
            TransactionDirection::Debit,
            $transaction->direction
        );
    }

    public function test_document_and_transaction_must_use_same_currency(): void
    {
        $document =
            $this->createDocument(100000);

        $transaction =
            $this->createTransaction(100000);

        $transaction->update([
            'currency' => 'USD',
        ]);

        $transaction->refresh();

        try {
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: 100000,
                method: TaxDocumentMatchMethod::MANUAL
            );

            $this->fail(
                'Se esperaba una InvalidArgumentException.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'La moneda del documento tributario debe coincidir con la moneda del movimiento bancario.',
                $exception->getMessage()
            );
        }
    }

    public function test_same_currency_is_accepted(): void
    {
        $document =
            $this->createDocument(100000);

        $transaction =
            $this->createTransaction(100000);

        $payment =
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: 100000,
                method: TaxDocumentMatchMethod::MANUAL
            );

        $this->assertSame(
            'CLP',
            $document->currency
        );

        $this->assertSame(
            'CLP',
            $transaction->currency
        );

        $this->assertSame(
            TaxDocumentPaymentStatus::PROPOSED,
            $payment->status
        );
    }

    public function test_sale_accepts_credit_transaction(): void
    {
        $document =
            $this->createSaleDocument(100000);

        $transaction =
            $this->createTransaction(100000);

        $transaction->update([
            'direction' =>
                TransactionDirection::Credit,
        ]);

        $transaction->refresh();

        $payment =
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: 100000,
                method: TaxDocumentMatchMethod::MANUAL
            );

        $this->assertSame(
            TaxDocumentPaymentStatus::PROPOSED,
            $payment->status
        );

        $this->assertSame(
            TransactionDirection::Credit,
            $transaction->direction
        );
    }

    public function test_sale_cannot_be_proposed_against_debit_transaction(): void
    {
        $document =
            $this->createSaleDocument(100000);

        $transaction =
            $this->createTransaction(100000);

        try {
            $this->service->propose(
                document: $document,
                transaction: $transaction,
                amount: 100000,
                method: TaxDocumentMatchMethod::MANUAL
            );

            $this->fail(
                'Se esperaba una InvalidArgumentException.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Una factura de venta solo puede asociarse a un abono o crédito bancario.',
                $exception->getMessage()
            );
        }
    }

    private function createDocument(
        float $amount
    ): TaxDocument {
        return TaxDocument::create([
            'organization_id' =>
                $this->organization->id,

            'direction' =>
                TaxDocumentDirection::PURCHASE,

            'document_type' =>
                33,

            'folio' =>
                'PAY-' . uniqid(),

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
                '2026-10-31',

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
                TaxPaymentStatus::PENDING,

            'amount_paid' =>
                0,

            'amount_outstanding' =>
                $amount,

            'source' =>
                'test',
        ]);
    }

    private function createSaleDocument(
        float $amount
    ): TaxDocument {
        return TaxDocument::create([
            'organization_id' =>
                $this->organization->id,

            'direction' =>
                TaxDocumentDirection::SALE,

            'document_type' =>
                33,

            'folio' =>
                'SALE-' . uniqid(),

            'issuer_tax_id' =>
                $this->organization->tax_id,

            'issuer_name' =>
                $this->organization->name,

            'receiver_tax_id' =>
                '77666666-6',

            'receiver_name' =>
                'CLIENTE TEST SPA',

            'issue_date' =>
                '2026-10-01',

            'due_date' =>
                '2026-10-31',

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
                TaxPaymentStatus::PENDING,

            'amount_paid' =>
                0,

            'amount_outstanding' =>
                $amount,

            'source' =>
                'test',
        ]);
    }

    private function createTransaction(
        float $amount,
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
                'TRANSFERENCIA A PROVEEDOR',

            'description_normalized' =>
                'TRANSFERENCIA A PROVEEDOR',

            'reference' =>
                'PAY-' . uniqid(),

            'operation_number' =>
                (string) random_int(
                    1000000,
                    9999999
                ),

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
