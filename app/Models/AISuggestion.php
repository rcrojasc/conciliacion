<?php
namespace App\Models;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class AISuggestion extends Model {use HasUlids,BelongsToOrganization;protected $table='ai_suggestions';protected $guarded=[];protected function casts():array{return ['response'=>'array','confidence'=>'decimal:2'];}}
