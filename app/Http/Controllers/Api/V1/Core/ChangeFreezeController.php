<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\ChangeFreezeperiod;
use App\Services\Core\ChangeFreezeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChangeFreezeController extends Controller
{
    public function __construct(private readonly ChangeFreezeService $changeFreezeService)
    {
    }

    /**
     * List all freeze periods for the authenticated organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);
        $paginator      = $this->changeFreezeService->listFreezes($organizationId);

        return $this->paginated($paginator);
    }

    /**
     * Create a new change freeze period.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'reason'            => ['nullable', 'string'],
            'starts_at'         => ['required', 'date'],
            'ends_at'           => ['nullable', 'date', 'after:starts_at'],
            'scope'             => ['required', 'in:all,module'],
            'affected_modules'  => ['nullable', 'array'],
            'affected_modules.*' => ['string'],
            'bypass_roles'      => ['nullable', 'array'],
            'bypass_roles.*'    => ['string'],
            'bypass_permission' => ['nullable', 'string', 'max:100'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $freeze = $this->changeFreezeService->createFreeze($validated, auth()->id());

        return $this->created($freeze, 'Change freeze period created successfully.');
    }

    /**
     * Show a single freeze period.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $freeze = ChangeFreezeperiod::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->findOrFail($id);

        return $this->success($freeze);
    }

    /**
     * Deactivate / end a freeze period early.
     * POST /{id}/end
     */
    public function endFreeze(Request $request, int $id): JsonResponse
    {
        $this->changeFreezeService->endFreeze($id);

        return $this->success(null, 'Change freeze period ended.');
    }

    /**
     * Soft-delete a freeze period.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $freeze = ChangeFreezeperiod::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->findOrFail($id);

        $freeze->delete();

        return $this->noContent();
    }
}
