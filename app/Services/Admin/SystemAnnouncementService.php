<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin\AnnouncementRead;
use App\Models\Admin\SystemAnnouncement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SystemAnnouncementService
{
    /**
     * Create a new system announcement.
     */
    public function create(array $data): SystemAnnouncement
    {
        return DB::transaction(function () use ($data) {
            return SystemAnnouncement::create([
                'admin_id' => $data['admin_id'],
                'title' => $data['title'],
                'content' => $data['content'],
                'type' => $data['type'] ?? SystemAnnouncement::TYPE_INFO,
                'target_audience' => $data['target_audience'] ?? SystemAnnouncement::AUDIENCE_ALL,
                'target_organization_ids' => $data['target_organization_ids'] ?? null,
                'target_subscription_plans' => $data['target_subscription_plans'] ?? null,
                'is_dismissible' => $data['is_dismissible'] ?? true,
                'show_banner' => $data['show_banner'] ?? false,
                'banner_color' => $data['banner_color'] ?? null,
                'action_url' => $data['action_url'] ?? null,
                'action_text' => $data['action_text'] ?? null,
                'starts_at' => $data['starts_at'] ?? now(),
                'ends_at' => $data['ends_at'] ?? null,
                'is_active' => $data['is_active'] ?? false,
            ]);
        });
    }

    /**
     * Publish an announcement (set active and starts_at if needed).
     */
    public function publish(SystemAnnouncement $announcement): SystemAnnouncement
    {
        return DB::transaction(function () use ($announcement) {
            $announcement->update([
                'is_active' => true,
                'starts_at' => $announcement->starts_at ?? now(),
            ]);

            return $announcement->fresh();
        });
    }

    /**
     * Mark an announcement as read by a user.
     */
    public function markRead(int $announcementId, int $userId, bool $dismissed = false): AnnouncementRead
    {
        return AnnouncementRead::updateOrCreate(
            [
                'announcement_id' => $announcementId,
                'user_id' => $userId,
            ],
            [
                'is_dismissed' => $dismissed,
            ]
        );
    }

    /**
     * Get unread announcements for a user.
     */
    public function getUnread(int $userId, ?int $organizationId = null, ?string $subscriptionPlan = null): Collection
    {
        $readIds = AnnouncementRead::where('user_id', $userId)
            ->pluck('announcement_id');

        return SystemAnnouncement::active()
            ->whereNotIn('id', $readIds)
            ->where(function ($query) use ($organizationId, $subscriptionPlan) {
                $query->where('target_audience', SystemAnnouncement::AUDIENCE_ALL);

                if ($organizationId) {
                    $query->orWhere(function ($q) use ($organizationId) {
                        $q->where('target_audience', SystemAnnouncement::AUDIENCE_SPECIFIC)
                            ->whereJsonContains('target_organization_ids', $organizationId);
                    });

                    $query->orWhere('target_audience', SystemAnnouncement::AUDIENCE_ORGANIZATIONS);
                }

                if ($subscriptionPlan) {
                    $query->orWhere(function ($q) use ($subscriptionPlan) {
                        $q->whereNotNull('target_subscription_plans')
                            ->whereJsonContains('target_subscription_plans', $subscriptionPlan);
                    });
                }
            })
            ->orderByDesc('starts_at')
            ->get();
    }
}
