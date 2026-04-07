<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\UsageAlert;
use App\Models\Billing\UsageMetric;
use App\Models\Billing\UsageSnapshot;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function __construct(private BillingService $billingService) {}

    /**
     * Get current usage metrics for the organization.
     */
    public function index(): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $snapshot = UsageSnapshot::where('organization_id', $organizationId)->first();

        if (!$snapshot) {
            // Return a default usage snapshot if none exists
            return $this->success([
                'organization_id' => $organizationId,
                'users_count' => 0,
                'branches_count' => 0,
                'storage_used_mb' => 0,
                'invoices_this_month' => 0,
                'products_count' => 0,
                'customers_count' => 0,
                'employees_count' => 0,
                'api_calls_this_month' => 0,
            ]);
        }

        return $this->success($snapshot);
    }

    public function summary(): JsonResponse
    {
        $snapshot = $this->billingService->getUsageSummary(auth()->user()->organization_id);
        return $this->success($snapshot);
    }

    public function history(Request $request): JsonResponse
    {
        $metrics = UsageMetric::where('organization_id', auth()->user()->organization_id)
            ->when($request->input('metric_type'), fn ($q, $type) => $q->where('metric_type', $type))
            ->orderByDesc('metric_date')
            ->paginate($request->input('per_page', 30));

        return $this->paginated($metrics);
    }

    /**
     * Get usage alerts for the organization.
     */
    public function alerts(Request $request): JsonResponse
    {
        $alerts = UsageAlert::where('organization_id', auth()->user()->organization_id)
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return $this->success($alerts);
    }
}
