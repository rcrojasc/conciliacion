<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
final class BankTransactionController extends Controller { public function index(Request $r): JsonResponse { $q=BankTransaction::query()->latest('booking_date'); if($r->filled('status'))$q->where('status',$r->string('status')); if($r->filled('bank_account_id'))$q->where('bank_account_id',$r->string('bank_account_id')); return response()->json(['data'=>$q->paginate(50)]); } }
