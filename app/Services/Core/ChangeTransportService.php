<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\ChangeTransportLog;
use App\Models\Core\ChangeTransportObject;
use App\Models\Core\ChangeTransportObjectAssignment;
use App\Models\Core\ChangeTransportRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChangeTransportService
{
    public function createRequest(array $data): ChangeTransportRequest
    {
        return DB::transaction(function () use ($data): ChangeTransportRequest {
            $request = ChangeTransportRequest::create([
                'organization_id'    => $data['organization_id'],
                'request_number'     => $this->generateRequestNumber($data['organization_id']),
                'description'        => $data['description'],
                'request_type'       => $data['request_type'],
                'category'           => $data['category'],
                'target_environment' => $data['target_environment'],
                'status'             => ChangeTransportRequest::STATUS_OPEN,
                'created_by'         => $data['created_by'],
            ]);

            $this->logAction($request, ChangeTransportLog::ACTION_CREATED, $data['created_by'], 'Transport request created');

            return $request;
        });
    }

    public function addObject(ChangeTransportRequest $request, array $objectData): ChangeTransportObject
    {
        if (!$request->isOpen()) {
            throw new RuntimeException('Cannot add objects to a non-open transport request.');
        }

        $object = $request->objects()->create([
            'object_type' => $objectData['object_type'],
            'object_name' => $objectData['object_name'],
            'object_key'  => $objectData['object_key'] ?? null,
            'change_type' => $objectData['change_type'],
            'payload'     => $objectData['payload'] ?? null,
            'checksums'   => $objectData['checksums'] ?? null,
        ]);

        $userId = $objectData['user_id'] ?? null;
        $this->logAction($request, ChangeTransportLog::ACTION_OBJECT_ADDED, $userId, "Object added: {$objectData['object_name']}");

        return $object;
    }

    public function release(ChangeTransportRequest $request, int $userId): void
    {
        if (!$request->isOpen()) {
            throw new RuntimeException('Only open transport requests can be released.');
        }

        if ($request->objects()->count() === 0) {
            throw new RuntimeException('Cannot release a transport request with no objects.');
        }

        DB::transaction(function () use ($request, $userId): void {
            $request->update([
                'status'      => ChangeTransportRequest::STATUS_RELEASED,
                'released_by' => $userId,
                'released_at' => Carbon::now(),
            ]);

            $this->logAction($request, ChangeTransportLog::ACTION_RELEASED, $userId, 'Transport request released');
        });
    }

    public function import(ChangeTransportRequest $request, string $environment): void
    {
        if (!$request->isReleased()) {
            throw new RuntimeException('Only released transport requests can be imported.');
        }

        $this->logAction($request, ChangeTransportLog::ACTION_IMPORT_STARTED, null, "Import started for environment: {$environment}");

        DB::transaction(function () use ($request, $environment): void {
            try {
                $log = $this->simulateImport($request, $environment);

                $request->update([
                    'status'      => ChangeTransportRequest::STATUS_IMPORTED,
                    'imported_at' => Carbon::now(),
                    'import_log'  => $log,
                ]);

                $this->logAction($request, ChangeTransportLog::ACTION_IMPORTED, null, "Import completed for environment: {$environment}");
            } catch (\Throwable $e) {
                $request->update([
                    'status'     => ChangeTransportRequest::STATUS_FAILED,
                    'import_log' => $e->getMessage(),
                ]);

                $this->logAction($request, ChangeTransportLog::ACTION_FAILED, null, "Import failed: {$e->getMessage()}");

                throw $e;
            }
        });
    }

    public function rollback(ChangeTransportRequest $request): void
    {
        if ($request->status === ChangeTransportRequest::STATUS_OPEN) {
            throw new RuntimeException('Cannot rollback an open transport request.');
        }

        DB::transaction(function () use ($request): void {
            $request->update([
                'status'     => ChangeTransportRequest::STATUS_OPEN,
                'imported_at' => null,
                'import_log'  => null,
            ]);

            $this->logAction($request, ChangeTransportLog::ACTION_ROLLBACK, null, 'Transport request rolled back to open');
        });
    }

    public function getOpenRequests(int $organizationId): Collection
    {
        return ChangeTransportRequest::where('organization_id', $organizationId)
            ->where('status', ChangeTransportRequest::STATUS_OPEN)
            ->with(['creator', 'objects'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function getTransportHistory(int $organizationId): Collection
    {
        return ChangeTransportRequest::where('organization_id', $organizationId)
            ->with(['creator', 'releaser'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function logAction(
        ChangeTransportRequest $request,
        string $action,
        ?int $userId = null,
        string $message = ''
    ): void {
        ChangeTransportLog::create([
            'change_transport_request_id' => $request->id,
            'action'                      => $action,
            'performed_by'                => $userId,
            'environment'                 => $request->target_environment,
            'message'                     => $message,
            'created_at'                  => Carbon::now(),
        ]);
    }

    private function generateRequestNumber(int $organizationId): string
    {
        $prefix = 'TR';
        $year   = date('Y');
        $count  = ChangeTransportRequest::where('organization_id', $organizationId)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('%s%s%05d', $prefix, $year, $count);
    }

    private function simulateImport(ChangeTransportRequest $request, string $environment): string
    {
        $lines = ["Import log for request {$request->request_number} to {$environment}"];
        $lines[] = 'Started at: ' . Carbon::now()->toIso8601String();

        foreach ($request->objects as $object) {
            $lines[] = "[OK] {$object->change_type} {$object->object_type}: {$object->object_name}";
        }

        $lines[] = 'Completed at: ' . Carbon::now()->toIso8601String();

        return implode("\n", $lines);
    }
}
