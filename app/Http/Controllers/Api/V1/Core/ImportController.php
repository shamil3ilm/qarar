<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\ImportJob;
use App\Services\Core\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function __construct(
        protected ImportService $importService
    ) {}

    public function entityTypes(): JsonResponse
    {
        $types = ImportJob::getEntityTypes();

        $result = array_map(fn ($code, $config) => [
            'code'             => $code,
            'name'             => $config['name'],
            'module'           => $config['module'],
            'required_fields'  => $config['required_fields'],
            'fields'           => $config['fields'],
        ], array_keys($types), $types);

        return $this->success($result);
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file'        => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'entity_type' => 'required|string',
        ]);

        $user       = $request->user();
        $entityType = $validated['entity_type'];

        $types = ImportJob::getEntityTypes();
        if (!isset($types[$entityType])) {
            return $this->error('Invalid entity type', 'INVALID_ENTITY_TYPE', 400);
        }

        $module = $types[$entityType]['module'];
        if (!app(\App\Services\Core\ModuleService::class)->isModuleEnabled($user->organization_id, $module)) {
            return $this->forbidden("Module '{$module}' is not enabled");
        }

        try {
            $importJob = $this->importService->uploadFile($request->file('file'), $entityType, $user);

            return $this->created([
                'id'          => $importJob->id,
                'uuid'        => $importJob->uuid,
                'entity_type' => $importJob->entity_type,
                'file_name'   => $importJob->original_name,
                'status'      => $importJob->status,
            ], 'File uploaded successfully. Preview and map columns before processing.');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    public function preview(Request $request, string $uuid): JsonResponse
    {
        $importJob = ImportJob::where('uuid', $uuid)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        if ($importJob->status !== ImportJob::STATUS_PENDING) {
            return $this->error('Import has already been processed', 'INVALID_STATE', 400);
        }

        try {
            return $this->success($this->importService->previewFile($importJob));
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    public function configure(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'column_mapping'          => 'required|array',
            'options'                 => 'sometimes|array',
            'options.update_existing' => 'sometimes|boolean',
            'options.skip_errors'     => 'sometimes|boolean',
            'options.dry_run'         => 'sometimes|boolean',
        ]);

        $importJob = ImportJob::where('uuid', $uuid)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        if ($importJob->status !== ImportJob::STATUS_PENDING) {
            return $this->error('Import has already been processed', 'INVALID_STATE', 400);
        }

        $importJob->update([
            'column_mapping' => $validated['column_mapping'],
            'options'        => array_merge($importJob->options ?? [], $validated['options'] ?? []),
        ]);

        return $this->success([
            'import_id'  => $importJob->uuid,
            'validation' => $this->importService->validateImport($importJob),
        ]);
    }

    public function process(Request $request, string $uuid): JsonResponse
    {
        $importJob = ImportJob::where('uuid', $uuid)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        if (!in_array($importJob->status, [ImportJob::STATUS_PENDING, ImportJob::STATUS_VALIDATING])) {
            return $this->error('Import cannot be processed in current state', 'INVALID_STATE', 400);
        }

        if (!$importJob->column_mapping) {
            return $this->error('Column mapping is required. Call /configure first.', 'CONFIGURATION_REQUIRED', 400);
        }

        try {
            $importJob = $this->importService->processImport($importJob);
            $message   = $importJob->isCompleted()
                ? "Import completed: {$importJob->success_rows} succeeded, {$importJob->failed_rows} failed"
                : 'Import processing';

            return $this->success($this->importService->getStatus($importJob), $message);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    public function status(Request $request, string $uuid): JsonResponse
    {
        $importJob = ImportJob::where('uuid', $uuid)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        return $this->success($this->importService->getStatus($importJob));
    }

    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $importJob = ImportJob::where('uuid', $uuid)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        if ($this->importService->cancelImport($importJob)) {
            return $this->success(null, 'Import cancelled successfully');
        }

        return $this->error('Cannot cancel import in current state', 'INVALID_STATE', 400);
    }

    public function history(Request $request): JsonResponse
    {
        $user       = $request->user();
        $entityType = $request->get('entity_type');
        $limit      = min((int) $request->get('limit', 20), 100);

        $imports = $this->importService->getHistory($user->organization_id, $entityType, $limit);

        return $this->success($imports->map(fn ($import) => [
            'id'           => $import->id,
            'uuid'         => $import->uuid,
            'entity_type'  => $import->entity_type,
            'file_name'    => $import->original_name,
            'status'       => $import->status,
            'total_rows'   => $import->total_rows,
            'success_rows' => $import->success_rows,
            'failed_rows'  => $import->failed_rows,
            'created_at'   => $import->created_at->toIso8601String(),
            'completed_at' => $import->completed_at?->toIso8601String(),
        ]));
    }

    public function sampleTemplate(Request $request, string $entityType): mixed
    {
        $types = ImportJob::getEntityTypes();
        if (!isset($types[$entityType])) {
            return $this->error('Invalid entity type', 'INVALID_ENTITY_TYPE', 400);
        }

        try {
            $filePath = $this->importService->generateSampleFile($entityType);
            $fullPath = Storage::disk('local')->path($filePath);

            return response()->download($fullPath, "{$entityType}_import_template.xlsx")->deleteFileAfterSend();
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    public function templates(Request $request): JsonResponse
    {
        $user       = $request->user();
        $entityType = $request->get('entity_type');

        $templates = $this->importService->getTemplates($user->organization_id, $entityType);

        return $this->success($templates->map(fn ($t) => [
            'id'             => $t->id,
            'name'           => $t->name,
            'entity_type'    => $t->entity_type,
            'column_mapping' => $t->column_mapping,
            'options'        => $t->options,
            'is_default'     => $t->is_default,
        ]));
    }

    public function saveTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:100',
            'entity_type'    => 'required|string',
            'column_mapping' => 'required|array',
            'options'        => 'sometimes|array',
            'is_default'     => 'sometimes|boolean',
        ]);

        try {
            $template = $this->importService->saveTemplate(
                $request->user()->organization_id,
                $validated['name'],
                $validated['entity_type'],
                $validated['column_mapping'],
                $validated['options'] ?? [],
                $validated['is_default'] ?? false
            );

            return $this->created([
                'id'          => $template->id,
                'name'        => $template->name,
                'entity_type' => $template->entity_type,
            ], 'Template saved successfully');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }
}
