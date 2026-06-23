<?php

namespace CoreFoundation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpFoundation\Response;

/**
 * BroadcastAuthController
 *
 * Handles WebSocket channel authorization.
 * Specifically designed to work with httpOnly cookies by verifying the
 * session/cookie before authorizing the socket connection.
 */
class BroadcastAuthController extends BaseController
{
    /**
     * Authenticate the current user for the requested channel.
     */
    public function __invoke(Request $request): Response
    {
        // Broadcast::auth will use the 'api' guard if configured,
        // which now supports our httpOnly cookies.
        return Broadcast::auth($request);
    }
}
