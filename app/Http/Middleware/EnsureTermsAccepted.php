<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTermsAccepted
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        if ($user->id === 1 || $user->hasAcceptedTerms()) {
            return $next($request);
        }

        if ($request->routeIs('terms.pending') || $request->routeIs('terms.accept') || $request->routeIs('logout')) {
            return $next($request);
        }

        return redirect()
            ->route('terms.pending')
            ->with('info', 'Tu cuenta está pendiente de la confirmación de los términos y condiciones.');
    }
}
