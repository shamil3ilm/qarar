<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $payload = auth('api')->payload();

            if ($payload->get('is_impersonating') === true) {
                $request->attributes->set('impersonated_by_id', $payload->get('impersonated_by_id'));
                $request->attributes->set('impersonation_session_id', $payload->get('impersonation_session_id'));
            }
        } catch (\Throwable) {
            // No token or invalid token — skip silently
        }

        return $next($request);
    }
}