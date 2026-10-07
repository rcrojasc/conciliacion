<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;use Illuminate\Database\Eloquent\Model;
class AIFeedback extends Model {use HasUlids;protected $table='ai_feedback';protected $guarded=[];protected function casts():array{return ['corrected_data'=>'array'];}}
