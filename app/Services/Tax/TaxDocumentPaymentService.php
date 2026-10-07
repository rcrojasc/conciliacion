<?php

namespace App\Services\Tax;

use App\Enums\TaxDocumentMatchMethod;
use App\Enums\TaxDocumentPaymentStatus;
use App\Enums\TaxPaymentStatus;
use App\Models\BankTransaction;
use App\Models\TaxDocument;
use App\Models\TaxDocumentPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Enums\TaxDocumentDirection;
use App\Enums\TransactionDirection;
use InvalidArgumentException;
use RuntimeException;

class TaxDocumentPaymentService
{
    public function propose(
        TaxDocument $document,
        BankTransaction $transaction,
        float $amount,
        TaxDocumentMatchMethod $method,
        ?float $score = null,
        ?User $createdBy = null,
        ?array $metadata = null
    ): TaxDocumentPayment {
        return DB::transaction(function () use (
            $document,
            $transaction,
            $amount,
            $method,
            $score,
            $createdBy,
            $metadata
        ): TaxDocumentPayment {

            $this->assertSameOrganization(
                $document,
                $transaction
            );

            $this->assertCompatibleDirection(
                $document,
                $transaction
            );

            $this->assertCompatibleCurrency(
                $document,
                $transaction
            );

            $this->assertPositiveAmount(
                $amount
            );

            $existing = TaxDocumentPayment::withoutGlobalScopes()
                ->where(
                    'tax_document_id',
                    $document->id
                )
                ->where(
                    'bank_transaction_id',
                    $transaction->id
                )
                ->first();

            if ($existing) {
                throw new RuntimeException(
                    'Ya existe una aplicación entre este documento y el movimiento bancario.'
                );
            }

            return TaxDocumentPayment::create([
                'organization_id' =>
                    $document->organization_id,

                'tax_document_id' =>
                    $document->id,

                'bank_transaction_id' =>
                    $transaction->id,

                'amount_applied' =>
                    $amount,

                'match_score' =>
                    $score,

                'match_method' =>
                    $method,

                'status' =>
                    TaxDocumentPaymentStatus::PROPOSED,

                'matched_at' =>
                    now(),

                'created_by' =>
                    $createdBy?->id,

                'metadata' =>
                    $metadata,
            ]);
        });
    }

    public function approve(
        TaxDocumentPayment $payment,
        ?User $approvedBy = null
    ): TaxDocumentPayment {
        return DB::transaction(function () use (
            $payment,
            $approvedBy
        ): TaxDocumentPayment {
            /** @var TaxDocumentPayment $lockedPayment */
            $lockedPayment =
                TaxDocumentPayment::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

            if (
                $lockedPayment->status
                !== TaxDocumentPaymentStatus::PROPOSED
            ) {
                throw new RuntimeException(
                    'Solo una aplicación propuesta puede ser aprobada.'
                );
            }

            /** @var TaxDocument $document */
            $document =
                TaxDocument::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail(
                        $lockedPayment->tax_document_id
                    );

            /** @var BankTransaction $transaction */
            $transaction =
                BankTransaction::withoutGlobalScopes()
                    ->findOrFail(
                        $lockedPayment->bank_transaction_id
                    );

            $this->assertSameOrganization(
                $document,
                $transaction
            );

            $this->assertTransactionHasAvailableAmount(
                $lockedPayment,
                $transaction
            );

            $lockedPayment->update([
                'status' =>
                    TaxDocumentPaymentStatus::APPROVED,

                'approved_at' =>
                    now(),

                'approved_by' =>
                    $approvedBy?->id,
            ]);

            $this->recalculateDocument($document);

            return $lockedPayment->fresh();
        });
    }

    private function assertCompatibleDirection(
        TaxDocument $document,
        BankTransaction $transaction
    ): void {
        $expectedDirection =
            match ($document->direction) {
                TaxDocumentDirection::PURCHASE =>
                    TransactionDirection::Debit,

                TaxDocumentDirection::SALE =>
                    TransactionDirection::Credit,
            };

        if ($transaction->direction !== $expectedDirection) {
            throw new InvalidArgumentException(
                $document->direction
                    === TaxDocumentDirection::PURCHASE
                    ? 'Una factura de compra solo puede asociarse a un cargo o débito bancario.'
                    : 'Una factura de venta solo puede asociarse a un abono o crédito bancario.'
            );
        }
    }

    private function assertCompatibleCurrency(
        TaxDocument $document,
        BankTransaction $transaction
    ): void {
        $documentCurrency =
            strtoupper(
                trim(
                    (string) $document->currency
                )
            );

        $transactionCurrency =
            strtoupper(
                trim(
                    (string) $transaction->currency
                )
            );

        if (
            $documentCurrency === ''
            ||
            $transactionCurrency === ''
            ||
            $documentCurrency !== $transactionCurrency
        ) {
            throw new InvalidArgumentException(
                'La moneda del documento tributario debe coincidir con la moneda del movimiento bancario.'
            );
        }
    }

    public function reject(
        TaxDocumentPayment $payment
    ): TaxDocumentPayment {
        return DB::transaction(function () use (
            $payment
        ): TaxDocumentPayment {
            /** @var TaxDocumentPayment $lockedPayment */
            $lockedPayment =
                TaxDocumentPayment::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

            if (
                $lockedPayment->status
                !== TaxDocumentPaymentStatus::PROPOSED
            ) {
                throw new RuntimeException(
                    'Solo una aplicación propuesta puede ser rechazada.'
                );
            }

            $lockedPayment->update([
                'status' =>
                    TaxDocumentPaymentStatus::REJECTED,
            ]);

            return $lockedPayment->fresh();
        });
    }

    public function reverse(
        TaxDocumentPayment $payment
    ): TaxDocumentPayment {
        return DB::transaction(function () use (
            $payment
        ): TaxDocumentPayment {
            /** @var TaxDocumentPayment $lockedPayment */
            $lockedPayment =
                TaxDocumentPayment::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

            if (
                $lockedPayment->status
                !== TaxDocumentPaymentStatus::APPROVED
            ) {
                throw new RuntimeException(
                    'Solo una aplicación aprobada puede ser reversada.'
                );
            }

            /** @var TaxDocument $document */
            $document =
                TaxDocument::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail(
                        $lockedPayment->tax_document_id
                    );

            $lockedPayment->update([
                'status' =>
                    TaxDocumentPaymentStatus::REVERSED,
            ]);

            $this->recalculateDocument($document);

            return $lockedPayment->fresh();
        });
    }

    public function recalculateDocument(
        TaxDocument $document
    ): TaxDocument {
        /** @var TaxDocument $freshDocument */
        $freshDocument =
            TaxDocument::withoutGlobalScopes()
                ->findOrFail($document->id);

        $total =
            round(
                (float) $freshDocument->total_amount,
                4
            );

        $paid =
            round(
                (float)
                TaxDocumentPayment::withoutGlobalScopes()
                    ->where(
                        'tax_document_id',
                        $freshDocument->id
                    )
                    ->where(
                        'status',
                        TaxDocumentPaymentStatus::APPROVED->value
                    )
                    ->sum('amount_applied'),
                4
            );

        /*
         * amount_outstanding representa deuda pendiente.
         * Nunca debe ser negativo.
         */
        $outstanding =
            round(
                max(
                    $total - $paid,
                    0
                ),
                4
            );

        $status =
            $this->paymentStatus(
                $total,
                $paid
            );

        $freshDocument->update([
            'amount_paid' =>
                $paid,

            'amount_outstanding' =>
                $outstanding,

            'payment_status' =>
                $status,
        ]);

        return $freshDocument->fresh();
    }

    private function paymentStatus(
        float $total,
        float $paid
    ): TaxPaymentStatus {
        $tolerance = 0.01;

        if ($paid <= $tolerance) {
            return TaxPaymentStatus::PENDING;
        }

        if ($paid < ($total - $tolerance)) {
            return TaxPaymentStatus::PARTIAL;
        }

        if (
            abs($paid - $total)
            <= $tolerance
        ) {
            return TaxPaymentStatus::PAID;
        }

        return TaxPaymentStatus::OVERPAID;
    }

    private function assertSameOrganization(
        TaxDocument $document,
        BankTransaction $transaction
    ): void {
        if (
            $document->organization_id
            !== $transaction->organization_id
        ) {
            throw new InvalidArgumentException(
                'El documento tributario y el movimiento bancario pertenecen a organizaciones diferentes.'
            );
        }
    }

    private function assertPositiveAmount(
        float $amount
    ): void {
        if ($amount <= 0) {
            throw new InvalidArgumentException(
                'El monto aplicado debe ser mayor que cero.'
            );
        }
    }

    private function assertTransactionHasAvailableAmount(
        TaxDocumentPayment $payment,
        BankTransaction $transaction
    ): void {
        $transactionAmount =
            round(
                abs((float) $transaction->amount),
                4
            );

        $alreadyApplied =
            round(
                (float)
                TaxDocumentPayment::withoutGlobalScopes()
                    ->where(
                        'bank_transaction_id',
                        $transaction->id
                    )
                    ->where(
                        'status',
                        TaxDocumentPaymentStatus::APPROVED->value
                    )
                    ->where(
                        'id',
                        '!=',
                        $payment->id
                    )
                    ->sum('amount_applied'),
                4
            );

        $amountToApprove =
            round(
                (float) $payment->amount_applied,
                4
            );

        $available =
            round(
                max(
                    $transactionAmount - $alreadyApplied,
                    0
                ),
                4
            );

        if ($amountToApprove > ($available + 0.01)) {
            throw new RuntimeException(
                'El monto aplicado supera el saldo disponible del movimiento bancario.'
            );
        }
    }
}
