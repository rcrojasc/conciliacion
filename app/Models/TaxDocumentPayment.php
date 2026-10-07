<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\TaxDocumentMatchMethod;
use App\Enums\TaxDocumentPaymentStatus;

class TaxDocumentPayment extends Model
{
    use HasUlids, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'tax_document_id',
        'bank_transaction_id',
        'amount_applied',
        'match_score',
        'match_method',
        'status',
        'matched_at',
        'approved_at',
        'created_by',
        'approved_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_applied' => 'decimal:4',
            'match_score' => 'decimal:2',
            'matched_at' => 'datetime',
            'approved_at' => 'datetime',
            'metadata' => 'array',

            'match_method' => TaxDocumentMatchMethod::class,
            'status' => TaxDocumentPaymentStatus::class,
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(
            TaxDocument::class,
            'tax_document_id'
        );
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(
            BankTransaction::class,
            'bank_transaction_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }
}
