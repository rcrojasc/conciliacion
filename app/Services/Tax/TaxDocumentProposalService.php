<?php

namespace App\Services\Tax;

use App\Enums\TaxDocumentMatchMethod;
use App\Enums\TaxDocumentPaymentStatus;
use App\Enums\TaxPaymentStatus;
use App\Models\BankTransaction;
use App\Models\TaxDocument;
use App\Models\TaxDocumentPayment;
use Illuminate\Support\Collection;

class TaxDocumentProposalService
{
    private const HIGH_CONFIDENCE_SCORE = 90.0;

    private const AMBIGUITY_MARGIN = 5.0;

    private const ALGORITHM_VERSION = 2;

    public function __construct(
        private readonly TaxDocumentBankMatcher $matcher,
        private readonly TaxDocumentPaymentService $paymentService
    ) {
    }

    /**
     * Analiza un documento tributario y genera una propuesta
     * solamente cuando existe un candidato bancario de alta
     * confianza, inequívoco y con saldo disponible.
     *
     * Este servicio NUNCA aprueba automáticamente.
     *
     * @return array{
     *     status:string,
     *     document_id:string,
     *     proposal:TaxDocumentPayment|null,
     *     candidate:array|null,
     *     candidates_count:int,
     *     message:string
     * }
     */
    public function generate(
        TaxDocument $document
    ): array {
        $document->refresh();

        if (
            $document->payment_status
            === TaxPaymentStatus::CANCELLED
        ) {
            return $this->result(
                status: 'cancelled',
                document: $document,
                message: 'El documento se encuentra anulado.'
            );
        }

        if (
            $document->payment_status
            === TaxPaymentStatus::PAID
        ) {
            return $this->result(
                status: 'already_paid',
                document: $document,
                message: 'El documento ya se encuentra pagado.'
            );
        }

        /*
         * Una propuesta pendiente requiere resolución
         * humana antes de generar otra.
         */
        $existingProposal =
            TaxDocumentPayment::withoutGlobalScopes()
                ->where(
                    'organization_id',
                    $document->organization_id
                )
                ->where(
                    'tax_document_id',
                    $document->id
                )
                ->where(
                    'status',
                    TaxDocumentPaymentStatus::PROPOSED->value
                )
                ->first();

        if ($existingProposal) {
            return $this->result(
                status: 'existing_proposal',
                document: $document,
                proposal: $existingProposal,
                message: 'El documento ya posee una propuesta pendiente.'
            );
        }

        $outstanding =
            round(
                (float) $document->amount_outstanding,
                4
            );

        if ($outstanding <= 0.01) {
            return $this->result(
                status: 'no_outstanding_balance',
                document: $document,
                message: 'El documento no posee saldo pendiente.'
            );
        }

        /** @var Collection<int, array> $candidates */
        $candidates =
            $this->matcher
                ->findCandidates($document)
                ->values();

        if ($candidates->isEmpty()) {
            return $this->result(
                status: 'no_candidates',
                document: $document,
                candidatesCount: 0,
                message: 'No se encontraron movimientos bancarios candidatos.'
            );
        }

        /*
         * Solamente candidatos con score suficiente
         * pueden generar propuestas automáticas.
         */
        $highConfidence =
            $candidates
                ->filter(
                    fn (array $candidate): bool =>
                        (float) ($candidate['score'] ?? 0)
                        >= self::HIGH_CONFIDENCE_SCORE
                )
                ->sortByDesc(
                    fn (array $candidate): float =>
                        (float) ($candidate['score'] ?? 0)
                )
                ->values();

        if ($highConfidence->isEmpty()) {
            return $this->result(
                status: 'low_confidence',
                document: $document,
                candidatesCount: $candidates->count(),
                message: 'Existen candidatos, pero ninguno alcanza el nivel de confianza requerido.'
            );
        }

        $best =
            $highConfidence->first();

        $second =
            $highConfidence->get(1);

        /*
         * Si dos candidatos son demasiado similares,
         * no elegimos automáticamente.
         */
        if ($second !== null) {
            $bestScore =
                (float) ($best['score'] ?? 0);

            $secondScore =
                (float) ($second['score'] ?? 0);

            if (
                ($bestScore - $secondScore)
                < self::AMBIGUITY_MARGIN
            ) {
                return $this->result(
                    status: 'ambiguous',
                    document: $document,
                    candidate: $best,
                    candidatesCount: $highConfidence->count(),
                    message: 'Existen múltiples candidatos de alta confianza y se requiere revisión manual.'
                );
            }
        }

        $transaction =
            $this->resolveTransaction($best);

        if (!$transaction) {
            return $this->result(
                status: 'invalid_candidate',
                document: $document,
                candidate: $best,
                candidatesCount: $candidates->count(),
                message: 'El candidato seleccionado no contiene un movimiento bancario válido.'
            );
        }

        if (
            $transaction->organization_id
            !== $document->organization_id
        ) {
            return $this->result(
                status: 'organization_mismatch',
                document: $document,
                candidate: $best,
                candidatesCount: $candidates->count(),
                message: 'El movimiento bancario pertenece a otra organización.'
            );
        }

        /*
         * SII-6.3C
         *
         * No usamos simplemente transaction->amount.
         *
         * Calculamos cuánto dinero del movimiento sigue
         * realmente disponible después de considerar
         * aplicaciones APPROVED.
         */
        $availability =
            $this->transactionAvailability(
                $transaction
            );

        $availableAmount =
            $availability['available'];

        if ($availableAmount <= 0.01) {
            return $this->result(
                status: 'transaction_fully_applied',
                document: $document,
                candidate: $best,
                candidatesCount: $candidates->count(),
                message: 'El movimiento bancario ya se encuentra completamente aplicado.'
            );
        }

        /*
         * Nunca proponemos más que:
         *
         * 1. saldo pendiente de la factura;
         * 2. saldo disponible real del movimiento.
         */
        $amountToApply =
            round(
                min(
                    $outstanding,
                    $availableAmount
                ),
                4
            );

        if ($amountToApply <= 0.01) {
            return $this->result(
                status: 'invalid_amount',
                document: $document,
                candidate: $best,
                candidatesCount: $candidates->count(),
                message: 'No existe un monto válido para proponer.'
            );
        }

        $score =
            (float) ($best['score'] ?? 0);

        $reasons =
            $best['reasons'] ?? [];

        $method =
            $this->resolveMatchMethod(
                $reasons,
                $score
            );

        $proposal =
            $this->paymentService->propose(
                document: $document,
                transaction: $transaction,
                amount: $amountToApply,
                method: $method,
                score: $score,
                metadata: [
                    'proposal_engine' => [
                        'version' =>
                            self::ALGORITHM_VERSION,

                        'automatic' =>
                            true,

                        'threshold' =>
                            self::HIGH_CONFIDENCE_SCORE,

                        'ambiguity_margin' =>
                            self::AMBIGUITY_MARGIN,

                        'score' =>
                            $score,

                        'reasons' =>
                            $reasons,

                        /*
                         * Dejamos trazabilidad financiera
                         * del saldo disponible utilizado
                         * para construir la propuesta.
                         */
                        'transaction_amount' =>
                            $availability['total'],

                        'transaction_approved_amount' =>
                            $availability['approved'],

                        'transaction_available_amount' =>
                            $availability['available'],

                        'document_outstanding_amount' =>
                            $outstanding,

                        'proposed_amount' =>
                            $amountToApply,

                        'generated_at' =>
                            now()->toIso8601String(),
                    ],
                ]
            );

        return $this->result(
            status: 'proposed',
            document: $document,
            proposal: $proposal,
            candidate: $best,
            candidatesCount: $candidates->count(),
            message: 'Propuesta automática creada correctamente.'
        );
    }

    /**
     * Determina el saldo real disponible de un
     * movimiento bancario.
     *
     * PROPOSED  -> no consume saldo.
     * APPROVED  -> consume saldo.
     * REJECTED  -> no consume saldo.
     * REVERSED  -> vuelve a liberar saldo.
     *
     * @return array{
     *     total:float,
     *     approved:float,
     *     available:float
     * }
     */
    private function transactionAvailability(
        BankTransaction $transaction
    ): array {
        $total =
            round(
                abs(
                    (float) $transaction->amount
                ),
                4
            );

        $approved =
            round(
                (float)
                TaxDocumentPayment::withoutGlobalScopes()
                    ->where(
                        'organization_id',
                        $transaction->organization_id
                    )
                    ->where(
                        'bank_transaction_id',
                        $transaction->id
                    )
                    ->where(
                        'status',
                        TaxDocumentPaymentStatus::APPROVED->value
                    )
                    ->sum('amount_applied'),
                4
            );

        $available =
            round(
                max(
                    $total - $approved,
                    0
                ),
                4
            );

        return [
            'total' =>
                $total,

            'approved' =>
                $approved,

            'available' =>
                $available,
        ];
    }

    private function resolveTransaction(
        array $candidate
    ): ?BankTransaction {
        $transaction =
            $candidate['transaction'] ?? null;

        if ($transaction instanceof BankTransaction) {
            return $transaction;
        }

        $transactionId =
            $candidate['transaction_id'] ?? null;

        if (!$transactionId) {
            return null;
        }

        return BankTransaction::withoutGlobalScopes()
            ->find($transactionId);
    }

    private function resolveMatchMethod(
        array $reasons,
        float $score
    ): TaxDocumentMatchMethod {
        $reasonText =
            mb_strtoupper(
                implode(
                    ' ',
                    array_map(
                        'strval',
                        $reasons
                    )
                )
            );

        if (
            str_contains(
                $reasonText,
                'RUT'
            )
            &&
            str_contains(
                $reasonText,
                'MONTO'
            )
        ) {
            return TaxDocumentMatchMethod::RUT_AMOUNT;
        }

        if (
            str_contains(
                $reasonText,
                'MONTO EXACTO'
            )
        ) {
            return TaxDocumentMatchMethod::EXACT_AMOUNT;
        }

        if (
            $score
            >= self::HIGH_CONFIDENCE_SCORE
        ) {
            return TaxDocumentMatchMethod::COMBINATION;
        }

        return TaxDocumentMatchMethod::AI_ASSISTED;
    }

    /**
     * @return array{
     *     status:string,
     *     document_id:string,
     *     proposal:TaxDocumentPayment|null,
     *     candidate:array|null,
     *     candidates_count:int,
     *     message:string
     * }
     */
    private function result(
        string $status,
        TaxDocument $document,
        ?TaxDocumentPayment $proposal = null,
        ?array $candidate = null,
        int $candidatesCount = 0,
        string $message = ''
    ): array {
        return [
            'status' =>
                $status,

            'document_id' =>
                $document->id,

            'proposal' =>
                $proposal,

            'candidate' =>
                $candidate,

            'candidates_count' =>
                $candidatesCount,

            'message' =>
                $message,
        ];
    }
}
