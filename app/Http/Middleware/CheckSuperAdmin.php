<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class CheckSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Authenticate from the JWT token directly to ensure the correct user
        // is resolved (avoids auth guard caching issues)
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            $user = null;
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Authentication required',
                ],
            ], 401);
        }

        if (!$user->is_super_admin) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Super admin access required',
                ],
            ], 403);
        }

        return $next($request);
    }
}
