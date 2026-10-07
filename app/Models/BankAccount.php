<?php
namespace App\Models;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class BankAccount extends Model { use HasUlids, BelongsToOrganization; protected $fillable=['organization_id','bank_id','external_id','account_type','currency','masked_number','name','status']; public function bank(): BelongsTo { return $this->belongsTo(Bank::class); } public function transactions(): HasMany { return $this->hasMany(BankTransaction::class); } }
