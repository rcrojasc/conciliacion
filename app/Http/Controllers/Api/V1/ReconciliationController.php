<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Reconciliation;
use Illuminate\Http\JsonResponse;
final class ReconciliationController extends Controller { public function index(): JsonResponse { return response()->json(['data'=>Reconciliation::query()->latest()->paginate(50)]); } }
