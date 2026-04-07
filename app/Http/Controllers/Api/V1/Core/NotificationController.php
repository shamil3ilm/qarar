<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\Notification;
use App\Services\Core\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Get user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $unreadOnly = $request->has('unread_only')
            ? $request->boolean('unread_only')
            : null;

        $notifications = $this->notificationService->getForUser(
            $user->id,
            $unreadOnly,
            $request->get('type'),
            (int) $request->get('limit', 50)
        );

        $unreadCount = $this->notificationService->getUnreadCount($user->id);

        return $this->success([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ], 'Notifications retrieved successfully');
    }

    /**
     * Get unread count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'unread_count' => $this->notificationService->getUnreadCount($user->id),
        ], 'Unread count retrieved successfully');
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $result = $this->notificationService->markAsRead($id, $user->id);

        if (!$result) {
            return $this->notFound('Notification not found');
        }

        return $this->success([
            'unread_count' => $this->notificationService->getUnreadCount($user->id),
        ], 'Notification marked as read');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = $this->notificationService->markAllAsRead(
            $user->id,
            $request->get('type')
        );

        return $this->success([
            'marked_count' => $count,
            'unread_count' => 0,
        ], "{$count} notifications marked as read");
    }

    /**
     * Delete notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $result = $this->notificationService->delete($id, $user->id);

        if (!$result) {
            return $this->notFound('Notification not found');
        }

        return $this->success(null, 'Notification deleted');
    }

    /**
     * Get notification preferences.
     */
    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $preferences = $this->notificationService->getUserPreferences($user->id);
        $types = Notification::getTypes();
        $typesGrouped = Notification::getTypesGrouped();

        return $this->success([
            'preferences' => $preferences,
            'available_types' => $types,
            'types_grouped' => $typesGrouped,
        ], 'Notification preferences retrieved successfully');
    }

    /**
     * Update notification preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'preferences' => 'required|array',
            'preferences.*.notification_type' => 'required|string',
            'preferences.*.email_enabled' => 'boolean',
            'preferences.*.database_enabled' => 'boolean',
            'preferences.*.push_enabled' => 'boolean',
            'preferences.*.sms_enabled' => 'boolean',
        ]);

        foreach ($request->get('preferences') as $pref) {
            $this->notificationService->updateUserPreference(
                $user->id,
                $pref['notification_type'],
                $pref['email_enabled'] ?? true,
                $pref['database_enabled'] ?? true,
                $pref['push_enabled'] ?? true,
                $pref['sms_enabled'] ?? false
            );
        }

        return $this->success(
            $this->notificationService->getUserPreferences($user->id),
            'Preferences updated successfully'
        );
    }

    /**
     * Update single preference.
     */
    public function updatePreference(Request $request, string $type): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'email_enabled' => 'boolean',
            'database_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
        ]);

        $preference = $this->notificationService->updateUserPreference(
            $user->id,
            $type,
            $request->boolean('email_enabled', true),
            $request->boolean('database_enabled', true),
            $request->boolean('push_enabled', true),
            $request->boolean('sms_enabled', false)
        );

        return $this->success($preference, 'Preference updated');
    }

    /**
     * Get available notification types.
     */
    public function types(Request $request): JsonResponse
    {
        return $this->success([
            'types' => Notification::getTypes(),
            'grouped' => Notification::getTypesGrouped(),
        ], 'Notification types retrieved successfully');
    }

    /**
     * Initialize default preferences for user.
     */
    public function initializePreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->notificationService->initializeUserPreferences($user);

        return $this->success(
            $this->notificationService->getUserPreferences($user->id),
            'Preferences initialized'
        );
    }
}
