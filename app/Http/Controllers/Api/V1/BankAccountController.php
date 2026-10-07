<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
final class BankAccountController extends Controller {
 public function index(): JsonResponse { return response()->json(['data'=>BankAccount::query()->with('bank')->orderBy('name')->paginate(25)]); }
 public function show(BankAccount $bankAccount): JsonResponse { $this->authorize('view',$bankAccount); return response()->json(['data'=>$bankAccount->load('bank')]); }
}
