<?php

namespace CoreFoundation\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AuthenticateWithCookie
 *
 * Reads the bearer token from an httpOnly cookie and injects it into the
 * Authorization header of the request.
 *
 * This allows Laravel's built-in authentication guards (Passport, Sanctum)
 * to continue working normally while the token is stored securely in a cookie.
 */
class AuthenticateWithCookie
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = config('core-foundation.auth.cookie_name', 'core_foundation_token');

        if (! $request->headers->has('Authorization') && $request->hasCookie($cookieName)) {
            $token = $request->cookie($cookieName);

            if (is_string($token) && $token !== '') {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
