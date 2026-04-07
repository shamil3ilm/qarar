<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Exceptions\ConcurrencyException;
use App\Exceptions\ERP\ValidationException as ErpValidationException;
use App\Exceptions\ERP\InvalidStateTransitionException;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     */
    protected $levels = [
        // Don't log these at error level
    ];

    /**
     * A list of the exception types that are not reported.
     */
    protected $dontReport = [
        ValidationException::class,
        AuthenticationException::class,
        ModelNotFoundException::class,
        TokenExpiredException::class,
        TokenInvalidException::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'secret',
        'token',
        'api_key',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Add custom reporting here (Sentry, etc.)
        });

        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $this->handleApiException($e, $request);
            }
        });
    }

    /**
     * Handle API exceptions with consistent JSON response
     */
    protected function handleApiException(Throwable $e, Request $request): JsonResponse
    {
        $requestId = (string) Str::uuid();

        // Log the full exception for debugging
        if ($this->shouldReport($e)) {
            Log::error('API Exception', [
                'request_id' => $requestId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => auth()->id(),
                // Don't log sensitive input
                'input_keys' => array_keys($request->except($this->dontFlash)),
            ]);
        }

        // Note: ValidationException, AuthenticationException, ModelNotFoundException,
        // NotFoundHttpException, AccessDeniedHttpException, QueryException, and
        // \InvalidArgumentException are already handled in bootstrap/app.php and
        // take precedence over this handler. Only domain-specific and JWT exceptions
        // are handled here.
        return match (true) {
            $e instanceof TokenExpiredException => $this->tokenExpiredResponse($requestId),
            $e instanceof TokenInvalidException, $e instanceof JWTException => $this->tokenInvalidResponse($requestId),
            $e instanceof ErpValidationException => response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()],
                'meta' => ['request_id' => $requestId, 'timestamp' => now()->toISOString()],
            ], 422),
            $e instanceof ConcurrencyException => $this->concurrencyResponse($e, $requestId),
            $e instanceof InvalidStateTransitionException => $this->stateTransitionResponse($e, $requestId),
            $e instanceof MethodNotAllowedHttpException => $this->methodNotAllowedResponse($e, $requestId),
            $e instanceof TooManyRequestsHttpException => $this->rateLimitResponse($e, $requestId),
            default => $this->serverErrorResponse($e, $requestId),
        };
    }

    protected function tokenExpiredResponse(string $requestId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TOKEN_EXPIRED',
                'message' => 'Token has expired.',
            ],
            'meta' => [
                'request_id' => $requestId,
                'timestamp' => now()->toISOString(),
            ],
        ], 401);
    }

    protected function tokenInvalidResponse(string $requestId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TOKEN_INVALID',
                'message' => 'Token is invalid.',
            ],
            'meta' => [
                'request_id' => $requestId,
                'timestamp' => now()->toISOString(),
            ],
        ], 401);
    }

    protected function methodNotAllowedResponse(MethodNotAllowedHttpException $e, string $requestId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'The requested HTTP method is not allowed for this endpoint.',
                'allowed_methods' => $e->getHeaders()['Allow'] ?? null,
            ],
            'meta' => [
                'request_id' => $requestId,
                'timestamp' => now()->toISOString(),
            ],
        ], 405);
    }

    protected function concurrencyResponse(ConcurrencyException $e, string $requestId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'CONCURRENCY_CONFLICT',
                'message' => $e->getMessage(),
                'current_version' => $e->getCurrentVersion(),
            ],
            'meta' => [
                'request_id' => $requestId,
                'timestamp' => now()->toISOString(),
            ],
        ], 409);
    }

    protected function stateTransitionResponse(InvalidStateTransitionException $e, string $requestId): JsonResponse
    {
        $ctx = $e->getContext();

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INVALID_STATE_TRANSITION',
                'message' => $e->getMessage(),
                'current_state' => $ctx['current_state'] ?? null,
                'attempted_state' => $ctx['target_state'] ?? null,
                'allowed_states' => $ctx['allowed_transitions'] ?? null,
            ],
            'meta' => [
                'request_id' => $requestId,
                'timestamp' => now()->toISOString(),
            ],
        ], 422);
    }

    protected function rateLimitResponse(TooManyRequestsHttpException $e, string $requestId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $e->getHeaders()['Retry-After'] ?? null,
            ],
            'meta' => [
                'request_id' => $requestId,
                'timestamp' => now()->toISOString(),
            ],
        ], 429);
    }

    protected function serverErrorResponse(Throwable $e, string $requestId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'SERVER_ERROR',
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : 'An unexpected error occurred.',
                'details' => config('app.debug') ? [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ],
            'meta' => [
                'request_id' => $requestId,
                'timestamp' => now()->toISOString(),
            ],
        ], 500);
    }
}
