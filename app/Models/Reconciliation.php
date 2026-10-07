<?php
namespace App\Models;
use App\Enums\ReconciliationStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Reconciliation extends Model { use HasUlids, BelongsToOrganization; protected $guarded=[]; protected function casts(): array { return ['confidence_score'=>'decimal:2','approved_at'=>'datetime','reversed_at'=>'datetime','reviewed_at'=>'datetime','requires_checker'=>'boolean','total_amount'=>'decimal:4','status'=>ReconciliationStatus::class]; } public function items(): HasMany { return $this->hasMany(ReconciliationItem::class); } public function differences(): HasMany { return $this->hasMany(ReconciliationDifference::class); } }
