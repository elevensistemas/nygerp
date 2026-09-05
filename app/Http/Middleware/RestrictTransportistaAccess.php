<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictTransportistaAccess
{
    /**
     * @var string[]
     */
    protected array $allowed = [
        'traffic.',
        'terms.',
        'password.',
        'logout',
        'portal-choferes.',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->isTransportista() && ! $user->isAdminOrSuper()) {
            $route = $request->route();
            $routeName = $route ? $route->getName() : null;

            if (! $this->isAllowed($routeName)) {
                $transportista = $user->transportistaProfile;
                $visibility = $transportista->portal_visibility ?? 'all';
                
                if ($visibility === 'settlements_only') {
                    return redirect()->route('portal-choferes.liquidaciones.index')
                        ->with('info', 'Solo podés acceder al módulo de liquidaciones.');
                }

                return redirect()->route('traffic.routes.index')
                    ->with('info', 'Solo podés acceder al módulo de tráfico y a tu configuración.');
            }
        }

        return $next($request);
    }

    protected function isAllowed(?string $routeName): bool
    {
        if (! $routeName) {
            return true;
        }

        foreach ($this->allowed as $allowed) {
            if (str_ends_with($allowed, '.')) {
                if (str_starts_with($routeName, $allowed)) {
                    return true;
                }
            } elseif ($routeName === $allowed) {
                return true;
            }
        }

        return false;
    }
}
