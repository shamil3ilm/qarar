<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds API versioning and lifecycle headers to every response.
 *
 * Headers added:
 *   X-API-Version: v1
 *   X-API-Supported-Versions: v1
 *   X-Request-ID: <uuid from request header or generated>
 */
class AddApiVersionHeader
{
    public function handle(Request $request, Closure $next, string $version = 'v1'): Response
    {
        $response = $next($request);

        $response->headers->set('X-API-Version', $version);
        $response->headers->set('X-API-Supported-Versions', 'v1');
        $response->headers->set('X-Request-ID', $request->header('X-Request-ID', (string) Str::uuid()));

        return $response;
    }
}
