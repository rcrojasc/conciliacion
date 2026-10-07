<?php

namespace App\Data\Tax;

final readonly class TaxDocumentLineData
{
    public function __construct(
        public int $lineNumber,

        public ?string $productCode,

        public string $description,

        public float $quantity,

        public ?string $unit,

        public float $unitPrice,

        public float $discountAmount,

        public float $surchargeAmount,

        public float $netAmount,

        public array $metadata = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'line_number' =>
                $this->lineNumber,

            'product_code' =>
                $this->productCode,

            'description' =>
                $this->description,

            'quantity' =>
                $this->quantity,

            'unit' =>
                $this->unit,

            'unit_price' =>
                $this->unitPrice,

            'discount_amount' =>
                $this->discountAmount,

            'surcharge_amount' =>
                $this->surchargeAmount,

            'net_amount' =>
                $this->netAmount,

            'metadata' =>
                $this->metadata,
        ];
    }
}
