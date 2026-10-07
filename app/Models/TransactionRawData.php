<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class TransactionRawData extends Model { use HasUlids; protected $table='transaction_raw_data'; protected $fillable=['bank_transaction_id','payload']; protected $casts=['payload'=>'array']; }
