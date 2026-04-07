<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\ChangeTransportRequest;
use App\Services\Core\ChangeTransportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ChangeTransportController extends Controller
{
    public function __construct(
        protected ChangeTransportService $service
    ) {}

    /**
     * GET /change-transport
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'             => 'nullable|string|in:open,released,imported,failed',
            'target_environment' => 'nullable|string|in:quality,production,staging',
            'category'           => 'nullable|string',
            'per_page'           => 'nullable|integer|min:1|max:100',
        ]);

        $organizationId = $this->organizationId($request);

        $query = ChangeTransportRequest::where('organization_id', $organizationId)
            ->with(['creator', 'objects'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('target_environment'), fn($q) => $q->where('target_environment', $request->get('target_environment')))
            ->when($request->filled('category'), fn($q) => $q->where('category', $request->get('category')));

        $results = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return $this->paginated($results);
    }

    /**
     * POST /change-transport
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description'        => 'required|string|max:200',
            'request_type'       => 'required|string|in:workbench,customizing,transport_of_copies',
            'category'           => 'required|string|in:feature,bugfix,configuration,data_migration',
            'target_environment' => 'required|string|in:quality,production,staging',
        ]);

        $validated['organization_id'] = $this->organizationId($request);
        $validated['created_by']      = $request->user()->id;

        $transportRequest = $this->service->createRequest($validated);

        return $this->created($transportRequest->load(['creator', 'objects']));
    }

    /**
     * GET /change-transport/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $transportRequest = ChangeTransportRequest::where('organization_id', $organizationId)
            ->with(['creator', 'releaser', 'objects', 'assignments.user'])
            ->findOrFail($id);

        return $this->success($transportRequest);
    }

    /**
     * PUT /change-transport/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $transportRequest = ChangeTransportRequest::where('organization_id', $organizationId)
            ->findOrFail($id);

        if (!$transportRequest->isOpen()) {
            return $this->error('Only open requests can be updated.', 'REQUEST_NOT_OPEN', 422);
        }

        $validated = $request->validate([
            'description'        => 'sometimes|string|max:200',
            'target_environment' => 'sometimes|string|in:quality,production,staging',
            'category'           => 'sometimes|string|in:feature,bugfix,configuration,data_migration',
        ]);

        $transportRequest->update($validated);

        return $this->success($transportRequest->fresh(['creator', 'objects']));
    }

    /**
     * GET /change-transport/{id}/objects
     */
    public function objects(Request $request, int $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $transportRequest = ChangeTransportRequest::where('organization_id', $organizationId)
            ->findOrFail($id);

        return $this->success($transportRequest->objects);
    }

    /**
     * POST /change-transport/{id}/objects
     */
    public function addObject(Request $request, int $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $transportRequest = ChangeTransportRequest::where('organization_id', $organizationId)
            ->findOrFail($id);

        $validated = $request->validate([
            'object_type' => 'required|string|in:migration,config,route,permission,setting',
            'object_name' => 'required|string|max:200',
            'object_key'  => 'nullable|string|max:200',
            'change_type' => 'required|string|in:create,modify,delete',
            'payload'     => 'nullable|array',
            'checksums'   => 'nullable|string|max:64',
        ]);

        $validated['user_id'] = $request->user()->id;

        try {
            $object = $this->service->addObject($transportRequest, $validated);
            return $this->created($object);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'OPERATION_FAILED', 422);
        }
    }

    /**
     * POST /change-transport/{id}/release
     */
    public function release(Request $request, int $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $transportRequest = ChangeTransportRequest::where('organization_id', $organizationId)
            ->findOrFail($id);

        try {
            $this->service->release($transportRequest, $request->user()->id);
            return $this->success($transportRequest->fresh(), 'Transport request released');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'RELEASE_FAILED', 422);
        }
    }

    /**
     * POST /change-transport/{id}/import
     */
    public function import(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'environment' => 'required|string|in:quality,production,staging',
        ]);

        $organizationId = $this->organizationId($request);

        $transportRequest = ChangeTransportRequest::where('organization_id', $organizationId)
            ->findOrFail($id);

        try {
            $this->service->import($transportRequest, $validated['environment']);
            return $this->success($transportRequest->fresh(), 'Transport request imported successfully');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'IMPORT_FAILED', 422);
        }
    }

    /**
     * POST /change-transport/{id}/rollback
     */
    public function rollback(Request $request, int $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $transportRequest = ChangeTransportRequest::where('organization_id', $organizationId)
            ->findOrFail($id);

        try {
            $this->service->rollback($transportRequest);
            return $this->success($transportRequest->fresh(), 'Transport request rolled back');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'ROLLBACK_FAILED', 422);
        }
    }

    /**
     * GET /change-transport/{id}/history
     */
    public function history(Request $request, int $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $transportRequest = ChangeTransportRequest::where('organization_id', $organizationId)
            ->findOrFail($id);

        $logs = $transportRequest->logs()
            ->with('performer')
            ->orderBy('created_at')
            ->get();

        return $this->success($logs);
    }

    /**
     * GET /change-transport/open
     */
    public function openRequests(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $requests = $this->service->getOpenRequests($organizationId);

        return $this->success($requests);
    }
}
