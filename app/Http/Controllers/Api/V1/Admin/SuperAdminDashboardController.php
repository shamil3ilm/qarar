<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SuperAdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminDashboardController extends Controller
{
    public function __construct(private SuperAdminDashboardService $dashboardService) {}

    public function overview(): JsonResponse
    {
        return $this->success($this->dashboardService->getOverview());
    }

    public function organizationStats(): JsonResponse
    {
        return $this->success($this->dashboardService->getOrganizationStats());
    }

    public function userStats(): JsonResponse
    {
        return $this->success($this->dashboardService->getUserStats());
    }

    public function revenueStats(): JsonResponse
    {
        return $this->success($this->dashboardService->getRevenueStats());
    }

    public function usageStats(): JsonResponse
    {
        return $this->success($this->dashboardService->getUsageStats());
    }

    public function supportStats(): JsonResponse
    {
        return $this->success($this->dashboardService->getSupportStats());
    }

    public function organizationDetail(int $id): JsonResponse
    {
        return $this->success($this->dashboardService->getOrganizationDetail($id));
    }

    public function organizationUsers(Request $request, int $organizationId): JsonResponse
    {
        return $this->paginated($this->dashboardService->getUsersForOrganization(
            $organizationId,
            $request->input('per_page', 20)
        ));
    }

    public function signupTrend(Request $request): JsonResponse
    {
        return $this->success($this->dashboardService->getSignupTrend($request->input('period', '6months')));
    }

    public function subscriptionDistribution(): JsonResponse
    {
        return $this->success($this->dashboardService->getSubscriptionDistribution());
    }

    public function topOrganizations(Request $request): JsonResponse
    {
        return $this->success($this->dashboardService->getTopOrganizations(
            $request->input('sort_by', 'revenue'),
            (int) $request->input('limit', 10)
        ));
    }
}
