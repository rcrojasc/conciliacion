<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class ReconciliationItem extends Model { use HasUlids; protected $guarded=[]; protected function casts(): array { return ['applied_amount'=>'decimal:4']; } }
