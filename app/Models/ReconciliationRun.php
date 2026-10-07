<?php
namespace App\Models;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class ReconciliationRun extends Model {
 use HasUlids, BelongsToOrganization;
 protected $guarded=[];
 protected function casts(): array { return ['started_at'=>'datetime','finished_at'=>'datetime','metrics'=>'array']; }
}
