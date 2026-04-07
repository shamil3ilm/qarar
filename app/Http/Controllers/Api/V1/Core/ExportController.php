<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\ExportJob;
use App\Services\Core\AsyncExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(
        protected AsyncExportService $exportService
    ) {}

    /**
     * Get available export entity types.
     */
    public function entityTypes(): JsonResponse
    {
        $types = ExportJob::getEntityTypes();

        $result = [];
        foreach ($types as $code => $config) {
            $result[] = [
                'code' => $code,
                'name' => $config['name'],
                'module' => $config['module'],
                'columns' => $config['columns'],
            ];
        }

        return $this->success($result);
    }

    /**
     * Create an export job.
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string',
            'format' => 'sometimes|string|in:xlsx,csv,pdf',
            'filters' => 'sometimes|array',
            'columns' => 'sometimes|array',
            'columns.*' => 'string',
        ]);

        $user = $request->user();
        $entityType = $request->get('entity_type');

        // Validate entity type exists
        $types = ExportJob::getEntityTypes();
        if (!isset($types[$entityType])) {
            return $this->error('Invalid entity type.', 'VALIDATION_ERROR', 400);
        }

        // Check module access
        $moduleService = app(\App\Services\Core\ModuleService::class);
        $module = $types[$entityType]['module'];
        if (!$moduleService->isModuleEnabled($user->organization_id, $module)) {
            return $this->error("Module '{$module}' is not enabled.", 'MODULE_DISABLED', 403);
        }

        try {
            $exportJob = $this->exportService->createExport(
                $entityType,
                $user,
                $request->get('format', 'xlsx'),
                $request->get('filters'),
                $request->get('columns')
            );

            // Process immediately (could be queued for large exports)
            $exportJob = $this->exportService->processExport($exportJob);

            $message = $exportJob->isReady()
                ? "Export completed: {$exportJob->total_records} records"
                : 'Export is being processed';

            return $this->created($this->exportService->getStatus($exportJob), $message);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Get export status.
     */
    public function status(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $exportJob = ExportJob::where('uuid', $uuid)
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        return $this->success($this->exportService->getStatus($exportJob));
    }

    /**
     * Download export file.
     */
    public function download(Request $request, string $uuid): mixed
    {
        $user = $request->user();

        $exportJob = ExportJob::where('uuid', $uuid)
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        if (!$exportJob->isReady()) {
            if ($exportJob->isExpired()) {
                return $this->error('Export has expired.', 'EXPIRED', 410);
            }

            if ($exportJob->status === ExportJob::STATUS_FAILED) {
                return $this->error('Export failed.', 'EXPORT_FAILED', 400);
            }

            return $this->error('Export is not ready for download.', 'NOT_READY', 400);
        }

        $filePath = $this->exportService->download($exportJob);

        if (!$filePath) {
            return $this->error('File not found.', 'NOT_FOUND', 404);
        }

        $mimeType = match ($exportJob->format) {
            'csv' => 'text/csv',
            'pdf' => 'application/pdf',
            default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };

        return response()->download($filePath, $exportJob->file_name, [
            'Content-Type' => $mimeType,
        ]);
    }

    /**
     * Get export history.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $entityType = $request->get('entity_type');
        $limit = min((int) $request->get('limit', 20), 100);

        $exports = $this->exportService->getHistory(
            $user->organization_id,
            $entityType,
            $limit
        );

        return $this->success($exports->map(fn ($export) => [
            'id' => $export->id,
            'uuid' => $export->uuid,
            'entity_type' => $export->entity_type,
            'format' => $export->format,
            'status' => $export->status,
            'total_records' => $export->total_records,
            'file_name' => $export->file_name,
            'file_size' => $export->file_size,
            'is_ready' => $export->isReady(),
            'is_expired' => $export->isExpired(),
            'download_url' => $export->getDownloadUrl(),
            'created_at' => $export->created_at->toIso8601String(),
            'expires_at' => $export->expires_at?->toIso8601String(),
        ]));
    }

    /**
     * Quick export endpoint - creates and returns download immediately.
     */
    public function quickExport(Request $request): mixed
    {
        $request->validate([
            'entity_type' => 'required|string',
            'format' => 'sometimes|string|in:xlsx,csv',
            'filters' => 'sometimes|array',
            'columns' => 'sometimes|array',
        ]);

        $user = $request->user();
        $entityType = $request->get('entity_type');

        // Validate entity type
        $types = ExportJob::getEntityTypes();
        if (!isset($types[$entityType])) {
            return $this->error('Invalid entity type.', 'VALIDATION_ERROR', 400);
        }

        try {
            $exportJob = $this->exportService->createExport(
                $entityType,
                $user,
                $request->get('format', 'xlsx'),
                $request->get('filters'),
                $request->get('columns')
            );

            $exportJob = $this->exportService->processExport($exportJob);

            if (!$exportJob->isReady()) {
                return $this->error('Export failed.', 'EXPORT_FAILED', 400);
            }

            $filePath = $this->exportService->download($exportJob);

            $mimeType = match ($exportJob->format) {
                'csv' => 'text/csv',
                default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            };

            return response()->download($filePath, $exportJob->file_name, [
                'Content-Type' => $mimeType,
            ]);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('An unexpected error occurred. Please try again.');
        }
    }
}
