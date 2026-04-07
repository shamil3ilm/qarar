<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Core\ChangeFreezeService;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class CheckChangeFreeeze
{
    public function __construct(private readonly ChangeFreezeService $changeFreezeService)
    {
    }

    /**
     * Handle an incoming request.
     *
     * Usage in routes:  ->middleware('check.freeze:accounting')
     *
     * GET and HEAD requests are always allowed through. Mutating requests
     * (POST, PUT, PATCH, DELETE) are blocked when an active change freeze
     * applies to the module and the user cannot bypass it.
     */
    public function handle(Request $request, Closure $next, string $module = 'all'): Response
    {
        // Read-only requests are never blocked
        if (in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Resolve the authenticated user from JWT
        $user = $this->resolveUser($request);

        if ($user === null) {
            // Let auth middleware handle the missing user
            return $next($request);
        }

        $organizationId = $user->organization_id;

        if ($organizationId === null) {
            return $next($request);
        }

        if ($this->changeFreezeService->isFrozen($organizationId, $module, $user)) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'CHANGE_FREEZE_ACTIVE',
                    'message' => 'Write operations are currently blocked by an active change freeze period.',
                ],
            ], 423);
        }

        return $next($request);
    }

    private function resolveUser(Request $request): ?\App\Models\User
    {
        try {
            $rawToken = $request->bearerToken();
            if ($rawToken) {
                $payload = JWTAuth::setToken($rawToken)->getPayload();
                $userId  = $payload->get('sub');

                return $userId ? \App\Models\User::find($userId) : null;
            }

            return $request->user();
        } catch (\Exception) {
            return null;
        }
    }
}
