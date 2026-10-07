<?php
namespace App\Models;
use App\Enums\DocumentStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class FinancialDocument extends Model { use HasUlids, BelongsToOrganization; protected $guarded=[]; protected function casts(): array { return ['issue_date'=>'date','due_date'=>'date','total_amount'=>'decimal:4','open_amount'=>'decimal:4','status'=>DocumentStatus::class]; } public function counterparty(): BelongsTo { return $this->belongsTo(Counterparty::class); } }
