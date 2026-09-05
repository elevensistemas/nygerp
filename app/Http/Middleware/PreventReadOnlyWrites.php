<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PreventReadOnlyWrites
{
    /**
     * HTTP verbs that represent write operations and must be blocked
     * for users with the read-only role.
     *
     * @var string[]
     */
    private array $writeMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->isReadOnly()) {
            $method = strtoupper($request->method());

            if (in_array($method, $this->writeMethods, true)) {
                abort(403, 'Tu rol es de solo lectura. No puedes crear, editar ni eliminar datos.');
            }
        }

        return $next($request);
    }
}
