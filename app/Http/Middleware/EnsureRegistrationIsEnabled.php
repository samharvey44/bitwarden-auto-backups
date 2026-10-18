<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hide Fortify's registration routes unless registration is enabled.
 */
class EnsureRegistrationIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->routeIs('register', 'register.store') && ! config('fortify.registration_enabled'), 404);

        return $next($request);
    }
}
