<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if ($user->isAdmin()) {
            $impersonatingTenantId = session('impersonating_tenant_id');

            if ($impersonatingTenantId) {
                $user->tenant_id = $impersonatingTenantId;
            } else {
                abort(403, 'Administradores devem impersonar um cliente para acessar esta área.');
            }
        }

        if (!$user->tenant_id) {
            abort(403, 'Usuário não está associado a um cliente.');
        }

        if ($user->tenant && !$user->tenant->is_active) {
            abort(403, 'Conta do cliente está inativa.');
        }

        return $next($request);
    }
}