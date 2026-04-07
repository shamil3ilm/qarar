<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\QInfoRecord;
use App\Services\Manufacturing\QInfoRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QInfoRecordController extends Controller
{
    public function __construct(private readonly QInfoRecordService $service) {}

    public function index(Request $request): JsonResponse
    {
        $records = $this->service->list($request->user()->organization_id, $request->query());
        return $this->success($records, 'Q-Info records retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_id'                => 'nullable|integer|exists:contacts,id',
            'product_id'               => 'required|integer|exists:products,id',
            'inspection_type'          => 'required|in:goods_receipt,in_process,final,delivery,returns',
            'skip_lot_plan_id'         => 'nullable|integer|exists:skip_lot_sampling_plans,id',
            'quality_plan_id'          => 'nullable|integer|exists:quality_plans,id',
            'is_active'                => 'boolean',
            'release_required'         => 'boolean',
            'cert_required'            => 'boolean',
            'cert_type'                => 'nullable|string|max:50',
            'inspection_interval_days' => 'nullable|integer|min:1',
            'last_inspection_date'     => 'nullable|date',
            'next_inspection_date'     => 'nullable|date',
            'notes'                    => 'nullable|string',
        ]);

        $record = $this->service->create($request->user()->organization_id, $data);
        return $this->created($record, 'Q-Info record created.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $record = QInfoRecord::where('organization_id', $request->user()->organization_id)
            ->with(['vendor', 'product', 'skipLotPlan', 'qualityPlan'])
            ->findOrFail($id);
        return $this->success($record, 'Q-Info record retrieved.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = QInfoRecord::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        $data = $request->validate([
            'vendor_id'                => 'nullable|integer|exists:contacts,id',
            'product_id'               => 'integer|exists:products,id',
            'inspection_type'          => 'in:goods_receipt,in_process,final,delivery,returns',
            'skip_lot_plan_id'         => 'nullable|integer|exists:skip_lot_sampling_plans,id',
            'quality_plan_id'          => 'nullable|integer|exists:quality_plans,id',
            'is_active'                => 'boolean',
            'release_required'         => 'boolean',
            'cert_required'            => 'boolean',
            'cert_type'                => 'nullable|string|max:50',
            'inspection_interval_days' => 'nullable|integer|min:1',
            'last_inspection_date'     => 'nullable|date',
            'next_inspection_date'     => 'nullable|date',
            'notes'                    => 'nullable|string',
        ]);

        $updated = $this->service->update($record, $data);
        return $this->success($updated, 'Q-Info record updated.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $record = QInfoRecord::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $record->delete();
        return $this->success(null, 'Q-Info record deleted.');
    }

    public function dueForInspection(Request $request): JsonResponse
    {
        $records = $this->service->getDueForInspection($request->user()->organization_id);
        return $this->success($records, 'Records due for inspection retrieved.');
    }
}
