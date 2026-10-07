<?php
namespace App\Models;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class ReconciliationException extends Model { use HasUlids, BelongsToOrganization; protected $guarded=[]; protected function casts(): array { return ['details'=>'array']; } }
