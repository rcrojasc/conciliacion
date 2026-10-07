<?php
namespace App\Http\Middleware;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class RequirePermission {
 public function handle(Request $request, Closure $next, string $permission): Response {
   $user=$request->user(); $org=app(TenantContext::class)->organization();
   abort_unless($user && $org,401);
   $membership=$user->organizations()->whereKey($org->id)->wherePivot('status','active')->first();
   abort_unless($membership,403,'No pertenece a la organización activa.');
   $roleId=$membership->pivot->role_id;
   $allowed=\App\Models\Role::query()->whereKey($roleId)->whereHas('permissions',fn($q)=>$q->where('code',$permission))->exists();
   abort_unless($allowed,403,'Permiso insuficiente.'); return $next($request);
 }
}
