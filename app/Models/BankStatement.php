<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatement extends Model
{
    use HasUlids, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'bank_account_id',
        'bank_import_id',
        'period_from',
        'period_to',
        'opening_balance',
        'closing_balance',
        'currency',
        'status',
        'statement_reference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'opening_balance' => 'decimal:4',
            'closing_balance' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(
            BankAccount::class,
            'bank_account_id'
        );
    }

    public function bankImport(): BelongsTo
    {
        return $this->belongsTo(
            BankImport::class,
            'bank_import_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Nueva relación N:N
    |--------------------------------------------------------------------------
    */

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(
            BankTransaction::class,
            'bank_statement_transactions',
            'bank_statement_id',
            'bank_transaction_id'
        )
            ->withPivot([
                'id',
                'organization_id',
                'position',
                'source_row',
                'balance_after_reported',
            ])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /*
    |--------------------------------------------------------------------------
    | Acceso directo a las apariciones
    |--------------------------------------------------------------------------
    */

    public function transactionLinks(): HasMany
    {
        return $this->hasMany(
            BankStatementTransaction::class,
            'bank_statement_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relación transitoria LEGACY
    |--------------------------------------------------------------------------
    |
    | Se mantiene mientras ProcessBankImport continúe escribiendo
    | bank_transactions.statement_id.
    |
    */

    public function legacyTransactions(): HasMany
    {
        return $this->hasMany(
            BankTransaction::class,
            'statement_id'
        );
    }
}
