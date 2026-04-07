<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\TokenBlacklistService;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class ValidateJwtToken
{
    public function __construct(
        private TokenBlacklistService $blacklistService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Explicitly set the token from the current request to ensure
            // the correct token is used even across consecutive requests.
            // This handles the case where the JWTAuth singleton retains
            // state from a previous request (e.g. in testing or queue workers).
            $rawToken = $request->bearerToken();
            if ($rawToken) {
                JWTAuth::setToken($rawToken);
            }

            // Parse and validate token
            $payload = JWTAuth::parseToken()->getPayload();
            $jti = $payload->get('jti');
            $userId = $payload->get('sub');
            $issuedAt = $payload->get('iat');

            // Check if token is blacklisted
            if ($this->blacklistService->isBlacklisted($jti)) {
                return $this->tokenInvalidResponse('Token has been revoked');
            }

            // Check if token was issued before a password change
            if ($this->blacklistService->wasIssuedBeforePasswordChange($userId, $issuedAt)) {
                return $this->tokenInvalidResponse('Token invalidated due to password change');
            }

            // Resolve user directly from the token payload to avoid
            // stale cached users from the auth guard singleton.
            $user = \App\Models\User::find($userId);
            if (!$user) {
                return $this->tokenInvalidResponse('User not found');
            }

            if (!$user->is_active) {
                return $this->tokenInvalidResponse('User account is deactivated');
            }

            if ($user->deleted_at !== null) {
                return $this->tokenInvalidResponse('User account has been deleted');
            }

            // Set the correct user on the auth guard to ensure that
            // auth()->user() returns the right user for this request.
            // This is necessary because the JWTGuard caches the user
            // from the first request and may return a stale user.
            auth()->guard('api')->setUser($user);

        } catch (TokenExpiredException $e) {
            return $this->tokenExpiredResponse();
        } catch (TokenInvalidException $e) {
            return $this->tokenInvalidResponse('Token is invalid');
        } catch (JWTException $e) {
            return $this->tokenMissingResponse();
        }

        return $next($request);
    }

    private function tokenInvalidResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TOKEN_INVALID',
                'message' => $message,
            ],
        ], 401);
    }

    private function tokenExpiredResponse(): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TOKEN_EXPIRED',
                'message' => 'Token has expired',
            ],
        ], 401);
    }

    private function tokenMissingResponse(): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TOKEN_MISSING',
                'message' => 'Authorization token not provided',
            ],
        ], 401);
    }
}
