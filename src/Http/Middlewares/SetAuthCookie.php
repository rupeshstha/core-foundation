<?php

namespace CoreFoundation\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * SetAuthCookie
 *
 * Captures the access_token from a JSON response (e.g., login or OAuth token
 * endpoint) and attaches it as an httpOnly, Secure cookie.
 */
class SetAuthCookie
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldAttachCookie($request, $response)) {
            $data = $response->getData(true);
            $token = $data['access_token'] ?? null;

            if ($token) {
                // Attach the cookie
                $response->withCookie(cookie(
                    name: config('core-foundation.auth.cookie_name', 'dubdubco_token'),
                    value: $token,
                    minutes: config('core-foundation.auth.cookie_ttl', 60 * 24), // 1 day default
                    path: '/',
                    domain: config('core-foundation.auth.cookie_domain'),
                    secure: true,
                    httpOnly: true,
                    sameSite: 'Lax'
                ));

                // Optional: Remove token from response body to prevent client-side storage
                if (config('core-foundation.auth.remove_token_from_body', false)) {
                    unset($data['access_token']);
                    $response->setData($data);
                }
            }
        }

        return $response;
    }

    /**
     * Determine if the cookie should be attached to this response.
     */
    protected function shouldAttachCookie(Request $request, Response $response): bool
    {
        return $response instanceof JsonResponse &&
               $response->isSuccessful();
    }
}
