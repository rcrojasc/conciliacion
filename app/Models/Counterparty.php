<?php
namespace App\Models;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class Counterparty extends Model { use HasUlids, BelongsToOrganization; protected $fillable=['organization_id','type','tax_id','legal_name','trade_name','email','status']; }
