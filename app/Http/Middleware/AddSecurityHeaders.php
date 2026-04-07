<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // NOTE: script-src uses 'strict-dynamic' for forward compatibility; production deployments
        // should replace this with a per-request nonce (e.g. script-src 'nonce-{random}') for
        // maximum XSS protection.
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'strict-dynamic'; object-src 'none'; frame-ancestors 'none';");
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
        }

        return $response;
    }
}
