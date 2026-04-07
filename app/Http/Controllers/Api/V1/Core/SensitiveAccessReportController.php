<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\SensitiveAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SensitiveAccessReportController extends Controller
{
    public function __construct(
        private readonly SensitiveAccessService $service
    ) {}

    /**
     * GET /sensitive-access/report
     *
     * Query params: model_type, user_id, action, from, to, per_page
     */
    public function report(Request $request): JsonResponse
    {
        $request->validate([
            'model_type' => ['nullable', 'string'],
            'user_id'    => ['nullable', 'integer'],
            'action'     => ['nullable', 'string', 'in:read,export,print'],
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date', 'after_or_equal:from'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->service->getAccessReport($request->all());

        return $this->success($paginator, 'Sensitive access report retrieved.');
    }

    /**
     * GET /sensitive-access/document/{type}/{id}
     *
     * Returns all access events for a specific document.
     */
    public function documentAccess(string $type, int $id): JsonResponse
    {
        $logs = $this->service->getAccessByDocument($type, $id);

        return $this->success([
            'model_type' => $type,
            'model_id'   => $id,
            'access_log' => $logs,
        ], 'Document access log retrieved.');
    }

    /**
     * GET /sensitive-access/suspicious
     *
     * Returns users with anomalous access patterns in the last 24 hours.
     */
    public function suspiciousActivity(Request $request): JsonResponse
    {
        $orgId    = $this->organizationId($request);
        $activity = $this->service->getSuspiciousActivity($orgId);

        return $this->success([
            'total'    => count($activity),
            'findings' => $activity,
        ], 'Suspicious activity report retrieved.');
    }
}
