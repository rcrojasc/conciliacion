<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class ReconciliationCandidate extends Model {
 use HasUlids; protected $guarded=[];
 protected function casts(): array { return ['score'=>'decimal:2','score_breakdown'=>'array','reasons'=>'array','candidate_refs'=>'array']; }
}
