<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantScope
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Se o usuário é admin, não aplicar scope de tenant
        if (!$user || $user->isAdmin()) {
            return $next($request);
        }

        // Se o usuário é client mas não tem tenant_id, redirecionar ou erro
        if ($user->isClient() && !$user->tenant_id) {
            abort(403, 'Usuário não associado a nenhuma organização.');
        }

        // Aplicar scope global para todos os models que precisam de tenant
        // Isso será implementado nos models específicos quando necessário

        return $next($request);
    }
}
