<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\GrcControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlLibraryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $controls = GrcControl::where('organization_id', $organizationId)
            ->with('controlOwner')
            ->when($request->filled('control_type'), fn ($q) => $q->where('control_type', $request->input('control_type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('module_reference'), fn ($q) => $q->where('module_reference', $request->input('module_reference')))
            ->orderBy('title')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($controls);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'control_code'     => ['required', 'string', 'max:30'],
            'title'            => ['required', 'string', 'max:200'],
            'description'      => ['nullable', 'string'],
            'control_type'     => ['required', 'in:preventive,detective,corrective,directive'],
            'control_category' => ['required', 'in:it,manual,automated,semi_automated'],
            'module_reference' => ['nullable', 'string', 'max:50'],
            'frequency'        => ['required', 'in:continuous,daily,weekly,monthly,quarterly,annual,ad_hoc'],
            'status'           => ['nullable', 'in:active,inactive,under_review'],
            'control_owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = (int) auth()->id();

        $control = GrcControl::create(array_merge($data, [
            'organization_id' => $organizationId,
            'created_by'      => $userId,
        ]));

        return $this->created($control, 'Control created');
    }

    public function show(Request $request, GrcControl $control): JsonResponse
    {
        abort_if($control->organization_id !== $this->organizationId($request), 403);

        return $this->success($control->load('controlOwner'));
    }

    public function update(Request $request, GrcControl $control): JsonResponse
    {
        abort_if($control->organization_id !== $this->organizationId($request), 403);

        $data = $request->validate([
            'title'            => ['sometimes', 'string', 'max:200'],
            'description'      => ['nullable', 'string'],
            'control_type'     => ['sometimes', 'in:preventive,detective,corrective,directive'],
            'control_category' => ['sometimes', 'in:it,manual,automated,semi_automated'],
            'module_reference' => ['nullable', 'string', 'max:50'],
            'frequency'        => ['sometimes', 'in:continuous,daily,weekly,monthly,quarterly,annual,ad_hoc'],
            'status'           => ['sometimes', 'in:active,inactive,under_review'],
            'control_owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $control->update($data);

        return $this->success($control->fresh('controlOwner'), 'Control updated');
    }

    public function destroy(Request $request, GrcControl $control): JsonResponse
    {
        abort_if($control->organization_id !== $this->organizationId($request), 403);

        $control->delete();

        return $this->success(null, 'Control deleted');
    }
}
