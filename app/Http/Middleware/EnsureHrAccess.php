<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureHrAccess
{
    /**
     * Handle an incoming request for HR module routes.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $level ('admin', 'manager', 'employee', 'ex_employee')
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $level = null)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Si es ex-empleado
        if ($user->isExEmployee()) {
            if ($level === 'ex_employee' || $request->routeIs('rrhh.portal.documents.download')) {
                return $next($request);
            }
            abort(403, 'Acceso restringido para ex-colaboradores. Solo puede acceder a sus documentos autorizados.');
        }

        // Administradores y Superusuarios tienen acceso total
        if ($user->isAdminOrSuper()) {
            return $next($request);
        }

        // Validación según nivel solicitado
        switch ($level) {
            case 'admin':
                if (!$user->isHrAdmin()) {
                    abort(403, 'No tienes permisos de Administrador de Recursos Humanos.');
                }
                break;

            case 'manager':
                if (!$user->isHrManager()) {
                    abort(403, 'No tienes permisos de Responsable / Manager.');
                }
                break;

            case 'employee':
                if (!$user->isHrEmployee() && !$user->isHrAdmin()) {
                    abort(403, 'No tienes un perfil de colaborador asignado en Recursos Humanos.');
                }
                break;

            default:
                // Acceso general al módulo (Administradores o Empleados)
                if (!$user->isHrAdmin() && !$user->isHrEmployee()) {
                    abort(403, 'No tienes acceso al módulo de Recursos Humanos.');
                }
                break;
        }

        return $next($request);
    }
}
