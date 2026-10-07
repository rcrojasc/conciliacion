<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
final class OrganizationController extends Controller { public function __invoke(TenantContext $ctx): JsonResponse { return response()->json(['data'=>$ctx->organization()]); } }
