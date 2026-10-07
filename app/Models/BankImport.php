<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankImport extends Model
{
    use HasUlids, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'bank_account_id',
        'source',
        'file_hash',
        'status',
        'rows_total',
        'rows_imported',
        'rows_duplicate',
        'rows_failed',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'rows_total' => 'integer',
        'rows_imported' => 'integer',
        'rows_duplicate' => 'integer',
        'rows_failed' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            BankAccount::class,
            'bank_account_id'
        );
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(
            BankTransaction::class,
            'bank_import_id'
        );
    }

    public function statement(): HasOne
    {
        return $this->hasOne(
            BankStatement::class,
            'bank_import_id'
        );
    }
}
