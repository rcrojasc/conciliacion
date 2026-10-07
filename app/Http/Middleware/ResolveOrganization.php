<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveOrganization
{
    public function __construct(
        private readonly TenantContext $tenants
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        /*
         * La ruta ya utiliza middleware "auth", pero mantenemos
         * esta comprobación defensiva.
         */
        abort_unless(
            $user !== null,
            401,
            'Usuario no autenticado.'
        );

        /*
         * Obtener la organización solicitada.
         *
         * API:
         * X-Organization-Id
         *
         * Web:
         * organization_id almacenado en sesión.
         */
        $organizationId = $request->header('X-Organization-Id')
            ?: $request->session()->get('organization_id');

        $organization = null;

        /*
         * Si existe una organización seleccionada,
         * comprobar que pertenece al usuario y que
         * la relación se encuentra activa.
         */
        if ($organizationId !== null && $organizationId !== '') {
            $organization = Organization::query()
                ->whereKey($organizationId)
                ->whereHas(
                    'users',
                    function ($query) use ($user): void {
                        $query
                            ->where('users.id', $user->getKey())
                            ->where(
                                'organization_user.status',
                                'active'
                            );
                    }
                )
                ->first();
        }

        /*
         * Si no existe una organización válida en sesión,
         * obtener las organizaciones activas del usuario.
         */
        if ($organization === null) {
            $organizations = $user
                ->organizations()
                ->wherePivot('status', 'active')
                ->get();

            abort_if(
                $organizations->isEmpty(),
                403,
                'El usuario no tiene organizaciones activas.'
            );

            /*
             * Cuando el usuario pertenece solamente a una
             * organización, seleccionarla automáticamente.
             */
            if ($organizations->count() === 1) {
                $organization = $organizations->first();

                $request->session()->put(
                    'organization_id',
                    $organization->getKey()
                );
            }
        }

        /*
         * Si existen varias organizaciones y ninguna fue
         * seleccionada, exigir una selección explícita.
         */
        abort_unless(
            $organization !== null,
            422,
            'Debe seleccionar una organización.'
        );

        /*
         * Establecer la organización activa durante
         * todo el ciclo de esta petición.
         */
        $this->tenants->set($organization);

        try {
            return $next($request);
        } finally {
            /*
             * Limpiar el contexto para evitar que el tenant
             * permanezca en memoria en procesos persistentes.
             */
            $this->tenants->clear();
        }
    }
}
