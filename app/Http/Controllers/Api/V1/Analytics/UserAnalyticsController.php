<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Analytics\UserActivityLog;
use App\Models\Analytics\UserClusterAssignment;
use App\Models\Analytics\UserFeatureUsage;
use App\Models\Analytics\UserSessionExtended;
use App\Models\User;
use App\Services\Analytics\UserClusteringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAnalyticsController extends Controller
{
    public function __construct(
        private readonly UserClusteringService $clusteringService,
    ) {}

    public function activityLogs(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $query = UserActivityLog::where('organization_id', $orgId)
            ->orderByDesc('created_at')
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('module'), fn($q) => $q->where('module', $request->input('module')))
            ->when($request->filled('from_date'), fn($q) => $q->where('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn($q) => $q->where('created_at', '<=', $request->input('to_date') . ' 23:59:59'));

        $logs = $query->paginate(50);

        return $this->paginated($logs);
    }

    public function featureUsage(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $query = UserFeatureUsage::where('organization_id', $orgId)
            ->orderByDesc('usage_date')
            ->when($request->filled('module'), fn($q) => $q->where('module', $request->input('module')))
            ->when($request->filled('from_date'), fn($q) => $q->where('usage_date', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn($q) => $q->where('usage_date', '<=', $request->input('to_date')))
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->integer('user_id')));

        $usage = $query->paginate(50);

        return $this->paginated($usage);
    }

    public function sessions(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $query = UserSessionExtended::where('organization_id', $orgId)
            ->orderByDesc('started_at')
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->integer('user_id')));

        $sessions = $query->paginate(50);

        return $this->paginated($sessions);
    }

    public function clusters(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $distribution = UserClusterAssignment::where('organization_id', $orgId)
            ->selectRaw('cluster_name, COUNT(DISTINCT user_id) as user_count')
            ->groupBy('cluster_name')
            ->orderByDesc('user_count')
            ->get();

        return $this->success($distribution);
    }

    public function userClusters(Request $request, int $id): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $user = User::where('id', $id)
            ->where('organization_id', $orgId)
            ->firstOrFail();

        $assignments = UserClusterAssignment::where('user_id', $user->id)
            ->orderByDesc('assigned_at')
            ->get(['cluster_name', 'algorithm', 'confidence', 'assigned_at', 'expires_at']);

        return $this->success($assignments);
    }

    public function dimensions(Request $request, int $id): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $user = User::where('id', $id)
            ->where('organization_id', $orgId)
            ->firstOrFail();

        $dimensions = $this->clusteringService->getDimensions($user);

        return $this->success($dimensions);
    }
}
