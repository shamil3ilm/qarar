<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Maintenance\MaintenanceNotification;
use App\Models\Maintenance\MaintenanceNotificationTask;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MaintenanceNotificationService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGen,
    ) {}

    public function list(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = MaintenanceNotification::where('organization_id', $organizationId)
            ->with(['equipment', 'reportedBy', 'responsible']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['notification_type'])) {
            $query->where('notification_type', $filters['notification_type']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['equipment_id'])) {
            $query->where('equipment_id', (int) $filters['equipment_id']);
        }

        if (!empty($filters['breakdown'])) {
            $query->where('breakdown', true);
        }

        return $query->latest()->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function create(int $organizationId, array $data, int $userId): MaintenanceNotification
    {
        return DB::transaction(function () use ($organizationId, $data, $userId): MaintenanceNotification {
            $notificationNumber = $this->numberGen->generate('MN', null, $organizationId);

            /** @var MaintenanceNotification $notification */
            $notification = MaintenanceNotification::create([
                ...$data,
                'organization_id'     => $organizationId,
                'notification_number' => $notificationNumber,
                'status'              => MaintenanceNotification::STATUS_OUTSTANDING,
                'reported_by'         => $userId,
                'created_by'          => $userId,
            ]);

            if (!empty($data['items'])) {
                foreach ($data['items'] as $i => $item) {
                    $notification->items()->create([
                        ...$item,
                        'item_number' => ($i + 1) * 10,
                    ]);
                }
            }

            return $notification->load(['items', 'tasks', 'equipment', 'reportedBy']);
        });
    }

    public function find(int $organizationId, string $uuid): MaintenanceNotification
    {
        return MaintenanceNotification::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->with(['items', 'tasks', 'equipment', 'reportedBy', 'responsible'])
            ->firstOrFail();
    }

    public function update(int $organizationId, string $uuid, array $data): MaintenanceNotification
    {
        $notification = MaintenanceNotification::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $notification->update($data);

        return $notification->fresh(['items', 'tasks', 'equipment']);
    }

    public function complete(int $organizationId, string $uuid, array $data, int $userId): MaintenanceNotification
    {
        return DB::transaction(function () use ($organizationId, $uuid, $data): MaintenanceNotification {
            $notification = MaintenanceNotification::where('organization_id', $organizationId)
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->firstOrFail();

            $notification->update([
                'status'          => MaintenanceNotification::STATUS_COMPLETED,
                'completed_at'    => now(),
                'completion_text' => $data['completion_text'] ?? null,
            ]);

            // Mark all outstanding tasks as completed
            $notification->tasks()
                ->where('status', 'outstanding')
                ->update(['status' => 'completed', 'completed_at' => now()]);

            return $notification->fresh(['items', 'tasks']);
        });
    }

    public function assignToOrder(int $organizationId, string $uuid, int $orderId): MaintenanceNotification
    {
        $notification = MaintenanceNotification::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $notification->update([
            'status'               => MaintenanceNotification::STATUS_ORDER_ASSIGNED,
            'maintenance_order_id' => $orderId,
        ]);

        return $notification->fresh();
    }

    public function addTask(int $organizationId, string $uuid, array $data): MaintenanceNotificationTask
    {
        $notification = MaintenanceNotification::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $lastTask   = $notification->tasks()->orderByDesc('task_number')->first();
        $taskNumber = (($lastTask?->task_number ?? 0) + 10);

        /** @var MaintenanceNotificationTask $task */
        $task = $notification->tasks()->create([
            ...$data,
            'task_number' => $taskNumber,
        ]);

        return $task;
    }

    /**
     * Return open notifications grouped by equipment for dashboard use.
     */
    public function getByEquipment(int $organizationId, int $equipmentId): array
    {
        $notifications = MaintenanceNotification::where('organization_id', $organizationId)
            ->where('equipment_id', $equipmentId)
            ->whereNotIn('status', [MaintenanceNotification::STATUS_COMPLETED])
            ->with(['items', 'tasks'])
            ->latest()
            ->get();

        return [
            'equipment_id'       => $equipmentId,
            'open_notifications' => $notifications->count(),
            'breakdown_count'    => $notifications->where('breakdown', true)->count(),
            'notifications'      => $notifications->values(),
        ];
    }
}
