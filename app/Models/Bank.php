<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class Bank extends Model { use HasUlids; protected $fillable=['code','name','country','status']; }
