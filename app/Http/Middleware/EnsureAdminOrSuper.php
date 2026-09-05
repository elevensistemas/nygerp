<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminOrSuper
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->isAdminOrSuper()) {
            return $next($request);
        }

        abort(403);
    }
}
