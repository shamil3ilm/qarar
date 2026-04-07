<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\CapaAction;
use App\Models\Manufacturing\CapaEffectivenessReview;
use App\Models\Manufacturing\CapaRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CapaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $capas = CapaRecord::where('organization_id', $request->user()->organization_id)
            ->with('owner')
            ->paginate(20);

        return $this->paginated($capas);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'capa_number'       => 'required|string|max:50|unique:capa_records',
            'capa_type'         => 'required|in:corrective,preventive',
            'problem_statement' => 'required|string',
            'root_cause'        => 'nullable|string',
            'priority'          => 'required|in:critical,high,medium,low',
            'owner_id'          => 'nullable|integer|exists:users,id',
            'target_close_date' => 'nullable|date',
            'source_type'       => 'nullable|string',
            'source_id'         => 'nullable|integer',
        ]);

        $data['uuid']            = (string) Str::uuid();
        $data['organization_id'] = $request->user()->organization_id;

        $capa = CapaRecord::create($data);

        return $this->created($capa);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $capa = CapaRecord::where('organization_id', $request->user()->organization_id)
            ->with(['owner', 'actions', 'effectivenessReviews'])
            ->findOrFail($id);

        return $this->success($capa);
    }

    public function addAction(Request $request, int $capaId): JsonResponse
    {
        $data = $request->validate([
            'action_number'  => 'required|string|max:20',
            'description'    => 'required|string',
            'assigned_to_id' => 'nullable|integer|exists:users,id',
            'due_date'       => 'required|date',
        ]);

        $capa   = CapaRecord::where('organization_id', $request->user()->organization_id)->findOrFail($capaId);
        $action = $capa->actions()->create(array_merge($data, ['uuid' => (string) Str::uuid()]));

        return $this->created($action);
    }

    public function completeAction(Request $request, int $capaId, int $actionId): JsonResponse
    {
        $capa   = CapaRecord::where('organization_id', $request->user()->organization_id)->findOrFail($capaId);
        $action = CapaAction::where('capa_record_id', $capa->id)->findOrFail($actionId);

        $data = $request->validate(['completion_notes' => 'nullable|string']);

        $action->update([
            'status'           => 'completed',
            'completed_date'   => now()->toDateString(),
            'completion_notes' => $data['completion_notes'] ?? null,
        ]);

        return $this->success($action, 'Action completed');
    }

    public function addEffectivenessReview(Request $request, int $capaId): JsonResponse
    {
        $data = $request->validate([
            'review_date'   => 'required|date',
            'effectiveness' => 'required|in:effective,partially_effective,not_effective',
            'evidence'      => 'nullable|string',
            'conclusions'   => 'nullable|string',
        ]);

        $capa   = CapaRecord::where('organization_id', $request->user()->organization_id)->findOrFail($capaId);
        $review = CapaEffectivenessReview::create(array_merge($data, [
            'uuid'           => (string) Str::uuid(),
            'capa_record_id' => $capa->id,
            'reviewed_by_id' => $request->user()->id,
        ]));

        if ($data['effectiveness'] === 'effective') {
            $capa->update(['status' => 'closed', 'actual_close_date' => now()->toDateString()]);
        }

        return $this->created($review);
    }
}
