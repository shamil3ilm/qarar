<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Core\Organization;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use ApiResponse;

    protected function organization(?Request $request = null): ?Organization
    {
        return $request?->attributes->get('organization');
    }

    protected function organizationId(?Request $request = null): ?int
    {
        return $this->organization($request)?->id ?? auth()->user()?->organization_id;
    }

    /**
     * Return a safe sort column from a request parameter.
     * Rejects any column not in the provided allowlist to prevent ORDER BY injection.
     *
     * @param  string|null  $requested  The column name from the request (e.g. $request->sort_by)
     * @param  string[]     $allowed    Allowlist of valid column names for this endpoint
     * @param  string       $default    Fallback column when the requested value is not allowed
     */
    protected function safeSortBy(?string $requested, array $allowed, string $default): string
    {
        return ($requested !== null && in_array($requested, $allowed, true))
            ? $requested
            : $default;
    }

    /**
     * Return a safe sort direction ('asc' or 'desc').
     */
    protected function safeSortOrder(?string $requested, string $default = 'asc'): string
    {
        return in_array(strtolower((string) $requested), ['asc', 'desc'], true)
            ? strtolower($requested)
            : $default;
    }

    /**
     * Execute a service action and return a JSON response.
     *
     * Encapsulates the common pattern:
     *   try { $result = $action(); }
     *   catch (\InvalidArgumentException $e) { return 422; }
     *   return $this->success($result, $message);
     *
     * Usage:
     *   return $this->tryAction(
     *       fn() => $this->service->approve($model, auth()->id()),
     *       'Resource approved successfully.'
     *   );
     */
    protected function tryAction(
        callable $action,
        string $successMessage = 'Success',
        string $errorCode = 'VALIDATION_ERROR',
        int $errorStatus = 422,
    ): JsonResponse {
        try {
            $result = $action();
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), $errorCode, $errorStatus);
        }

        return $this->success($result, $successMessage);
    }
}
