<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limit how often accounts can be registered, with the "registration" rate limiter.
 */
class ThrottleRegistration
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(private ThrottleRequests $throttle) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('register.store')) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, 'registration');
    }
}
