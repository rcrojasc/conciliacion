<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxDocumentLine extends Model
{
    use HasUlids, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'tax_document_id',
        'line_number',
        'product_code',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount_amount',
        'surcharge_amount',
        'net_subtotal',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'quantity' => 'decimal:6',
            'unit_price' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'surcharge_amount' => 'decimal:4',
            'net_subtotal' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(
            TaxDocument::class,
            'tax_document_id'
        );
    }
}
