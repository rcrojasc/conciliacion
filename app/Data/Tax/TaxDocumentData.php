<?php

namespace App\Data\Tax;

use App\Enums\TaxDocumentDirection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final readonly class TaxDocumentData
{
    /**
     * @param Collection<int, TaxDocumentLineData> $lines
     */
    public function __construct(
        public TaxDocumentDirection $direction,

        public int $documentType,

        public string $folio,

        public string $issuerTaxId,

        public ?string $issuerName,

        public string $receiverTaxId,

        public ?string $receiverName,

        public CarbonImmutable $issueDate,

        public ?CarbonImmutable $dueDate,

        public float $netAmount,

        public float $exemptAmount,

        public float $vatAmount,

        public float $totalAmount,

        public string $currency,

        public string $source,

        public ?string $externalId = null,

        public array $metadata = [],

        public Collection $lines = new Collection(),
    ) {
        if (trim($this->folio) === '') {
            throw new InvalidArgumentException(
                'El folio del documento tributario es obligatorio.'
            );
        }

        if (trim($this->issuerTaxId) === '') {
            throw new InvalidArgumentException(
                'El RUT del emisor es obligatorio.'
            );
        }

        if (trim($this->receiverTaxId) === '') {
            throw new InvalidArgumentException(
                'El RUT del receptor es obligatorio.'
            );
        }

        if ($this->documentType <= 0) {
            throw new InvalidArgumentException(
                'El tipo de documento tributario no es válido.'
            );
        }

        if ($this->totalAmount < 0) {
            throw new InvalidArgumentException(
                'El monto total del documento tributario no puede ser negativo.'
            );
        }

        if (trim($this->currency) === '') {
            throw new InvalidArgumentException(
                'La moneda del documento tributario es obligatoria.'
            );
        }

        if (trim($this->source) === '') {
            throw new InvalidArgumentException(
                'La fuente del documento tributario es obligatoria.'
            );
        }

        foreach ($this->lines as $line) {
            if (!$line instanceof TaxDocumentLineData) {
                throw new InvalidArgumentException(
                    'Todas las líneas deben ser instancias de TaxDocumentLineData.'
                );
            }
        }
    }

    public function normalizedCurrency(): string
    {
        return strtoupper(
            trim($this->currency)
        );
    }

    public function normalizedIssuerTaxId(): string
    {
        return $this->normalizeTaxId(
            $this->issuerTaxId
        );
    }

    public function normalizedReceiverTaxId(): string
    {
        return $this->normalizeTaxId(
            $this->receiverTaxId
        );
    }

    public function identityKey(): string
    {
        return implode(
            '|',
            [
                $this->normalizedIssuerTaxId(),
                $this->documentType,
                trim($this->folio),
            ]
        );
    }

    public function toArray(): array
    {
        return [
            'direction' =>
                $this->direction->value,

            'document_type' =>
                $this->documentType,

            'folio' =>
                trim($this->folio),

            'issuer_tax_id' =>
                $this->normalizedIssuerTaxId(),

            'issuer_name' =>
                $this->issuerName,

            'receiver_tax_id' =>
                $this->normalizedReceiverTaxId(),

            'receiver_name' =>
                $this->receiverName,

            'issue_date' =>
                $this->issueDate->toDateString(),

            'due_date' =>
                $this->dueDate?->toDateString(),

            'net_amount' =>
                $this->netAmount,

            'exempt_amount' =>
                $this->exemptAmount,

            'vat_amount' =>
                $this->vatAmount,

            'total_amount' =>
                $this->totalAmount,

            'currency' =>
                $this->normalizedCurrency(),

            'source' =>
                $this->source,

            'external_id' =>
                $this->externalId,

            'metadata' =>
                $this->metadata,

            'lines' =>
                $this->lines
                    ->map(
                        fn (
                            TaxDocumentLineData $line
                        ): array =>
                            $line->toArray()
                    )
                    ->values()
                    ->all(),
        ];
    }

    private function normalizeTaxId(
        string $taxId
    ): string {
        $normalized =
            strtoupper(
                trim($taxId)
            );

        $normalized =
            str_replace(
                ['.', ' '],
                '',
                $normalized
            );

        return $normalized;
    }
}
