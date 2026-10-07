<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\TaxDocumentDirection;
use App\Enums\TaxPaymentStatus;



class TaxDocument extends Model
{
    use HasUlids, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'direction',
        'document_type',
        'folio',
        'issuer_tax_id',
        'issuer_name',
        'receiver_tax_id',
        'receiver_name',
        'issue_date',
        'due_date',
        'net_amount',
        'exempt_amount',
        'vat_amount',
        'total_amount',
        'currency',
        'sii_status',
        'payment_status',
        'amount_paid',
        'amount_outstanding',
        'source',
        'external_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => 'integer',

            'direction' => TaxDocumentDirection::class,
            'payment_status' => TaxPaymentStatus::class,

            'issue_date' => 'date',
            'due_date' => 'date',

            'net_amount' => 'decimal:4',
            'exempt_amount' => 'decimal:4',
            'vat_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',

            'amount_paid' => 'decimal:4',
            'amount_outstanding' => 'decimal:4',

            'metadata' => 'array',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(
            TaxDocumentLine::class,
            'tax_document_id'
        )->orderBy('line_number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(
            TaxDocumentPayment::class,
            'tax_document_id'
        );
    }

    public function bankTransactions(): BelongsToMany
    {
        return $this->belongsToMany(
            BankTransaction::class,
            'tax_document_payments',
            'tax_document_id',
            'bank_transaction_id'
        )
            ->withPivot([
                'id',
                'organization_id',
                'amount_applied',
                'match_score',
                'match_method',
                'status',
                'matched_at',
                'approved_at',
                'created_by',
                'approved_by',
                'metadata',
            ])
            ->withTimestamps();
    }
}
