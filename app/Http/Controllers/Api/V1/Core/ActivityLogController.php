<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\UserSession;
use App\Services\Core\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    /**
     * List activity logs with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'     => 'nullable|integer|exists:users,id',
            'action'      => 'nullable|string|max:100',
            'entity_type' => 'nullable|string|max:100',
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        $logs = $this->activityLogService->getAll(
            $request->only([
                'user_id', 'action', 'entity_type', 'module', 'severity',
                'is_system', 'search', 'date_from', 'date_to',
            ]),
            $request->integer('per_page', 20)
        );

        return $this->paginated($logs);
    }

    /**
     * Get activity logs for a specific entity.
     */
    public function getForEntity(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string|max:100',
            'entity_id' => 'required|string|max:100',
            'action' => 'nullable|string|max:50',
        ]);

        $logs = $this->activityLogService->getForEntity(
            $request->input('entity_type'),
            $request->input('entity_id'),
            $request->integer('per_page', 20),
            $request->input('action')
        );

        return $this->paginated($logs);
    }

    /**
     * Get activity logs for a specific user.
     */
    public function getForUser(Request $request, int $userId): JsonResponse
    {
        $targetUser = \App\Models\User::find($userId);
        abort_unless($targetUser !== null, 404, 'User not found.');

        $authUser = $request->user();
        if (!$authUser->is_super_admin && $userId !== $authUser->id) {
            abort(403, 'You are not authorized to view another user\'s activity logs.');
        }

        $logs = $this->activityLogService->getForUser(
            $userId,
            $request->integer('per_page', 20),
            $request->input('action'),
            $request->input('module')
        );

        return $this->paginated($logs);
    }

    /**
     * Get active sessions for the current user.
     */
    public function sessions(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $sessions = $this->activityLogService->getUserSessions($userId);

        return $this->success($sessions);
    }

    /**
     * Get all active sessions (admin view).
     */
    public function allSessions(Request $request): JsonResponse
    {
        if (!$request->user()->is_super_admin) {
            abort(403, 'Only super-admins can view all sessions.');
        }

        $sessions = $this->activityLogService->getAllActiveSessions(
            $request->integer('per_page', 20)
        );

        return $this->paginated($sessions);
    }

    /**
     * Terminate a specific session.
     */
    public function terminateSession(Request $request, UserSession $session): JsonResponse
    {
        // Users can only terminate their own sessions unless admin
        $user = $request->user();
        if ($session->user_id !== $user->id && !$user->is_super_admin) {
            return $this->error('You cannot terminate other users\' sessions.', 'OPERATION_FAILED', 403);
        }

        $this->activityLogService->terminateSession($session);

        return $this->success(null, 'Session terminated successfully.');
    }

    /**
     * Get login history for the current user or a specific user.
     */
    public function loginHistory(Request $request): JsonResponse
    {
        $userId = $request->user()->is_super_admin
            ? $request->input('user_id', $request->user()->id)
            : $request->user()->id;

        $history = $this->activityLogService->getLoginHistory(
            (int) $userId,
            $request->integer('per_page', 20),
            $request->input('status')
        );

        return $this->paginated($history);
    }

    /**
     * Get recently viewed entities for the current user.
     */
    public function recentlyViewed(Request $request): JsonResponse
    {
        $views = $this->activityLogService->getRecentlyViewed(
            $request->user()->id,
            $request->integer('limit', 20),
            $request->input('entity_type')
        );

        return $this->success($views);
    }

    /**
     * Record an entity view.
     */
    public function recordView(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => 'required|string|max:100',
            'entity_id' => 'required|string|max:100',
            'entity_name' => 'nullable|string|max:255',
        ]);

        $view = $this->activityLogService->recordEntityView(
            $data['entity_type'],
            $data['entity_id'],
            $data['entity_name'] ?? null,
            $request->user()->id
        );

        return $this->success($view);
    }

    /**
     * Get popular entities across the organization.
     */
    public function popularEntities(Request $request): JsonResponse
    {
        $entities = $this->activityLogService->getPopularEntities(
            $this->organizationId($request),
            $request->integer('limit', 10),
            $request->input('entity_type'),
            $request->integer('days', 30)
        );

        return $this->success($entities);
    }

    /**
     * Get activity statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->activityLogService->getStatistics(
            $this->organizationId($request),
            $request->integer('days', 30)
        );

        return $this->success($stats);
    }
}
