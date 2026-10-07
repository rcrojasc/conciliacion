<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementTransaction extends Model
{
    use HasUlids, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'bank_statement_id',
        'bank_transaction_id',
        'position',
        'source_row',
        'balance_after_reported',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'source_row' => 'integer',
            'balance_after_reported' => 'decimal:4',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(
            BankStatement::class,
            'bank_statement_id'
        );
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(
            BankTransaction::class,
            'bank_transaction_id'
        );
    }
}
