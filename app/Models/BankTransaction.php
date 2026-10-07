<?php
namespace App\Models;
use App\Enums\BankTransactionStatus;
use App\Enums\TransactionDirection;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankTransaction extends Model { use HasUlids, BelongsToOrganization; protected $guarded=[]; protected function casts(): array { return ['booking_date'=>'date','value_date'=>'date','amount'=>'decimal:4','balance_after'=>'decimal:4','status'=>BankTransactionStatus::class,'direction'=>TransactionDirection::class]; } public function bankAccount(): BelongsTo { return $this->belongsTo(BankAccount::class); }

public function bankImport(): BelongsTo
{
    return $this->belongsTo(
        BankImport::class,
        'bank_import_id'
    );
}
public function statement(): BelongsTo
{
    return $this->belongsTo(
        BankStatement::class,
        'statement_id'
    );
}

public function statements(): BelongsToMany
{
    return $this->belongsToMany(
        BankStatement::class,
        'bank_statement_transactions',
        'bank_transaction_id',
        'bank_statement_id'
    )
        ->withPivot([
            'id',
            'organization_id',
            'position',
            'source_row',
            'balance_after_reported',
        ])
        ->withTimestamps();
}

public function statementLinks(): HasMany
{
    return $this->hasMany(
        BankStatementTransaction::class,
        'bank_transaction_id'
    );
}
}
