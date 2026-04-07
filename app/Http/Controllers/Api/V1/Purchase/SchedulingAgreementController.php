<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\SaDeliverySchedule;
use App\Models\Purchase\SchedulingAgreement;
use App\Services\Purchase\SchedulingAgreementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SchedulingAgreementController extends Controller
{
    public function __construct(private readonly SchedulingAgreementService $service) {}

    public function index(Request $request): JsonResponse
    {
        $agreements = $this->service->list(
            (int) Auth::user()->organization_id,
            $request->only(['vendor_id', 'product_id', 'status', 'per_page'])
        );

        return $this->success($agreements, 'Scheduling agreements retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id'        => 'required|integer|exists:contacts,id',
            'product_id'       => 'required|integer|exists:products,id',
            'agreement_number' => 'required|string|max:50',
            'valid_from'       => 'required|date',
            'valid_to'         => 'nullable|date|after_or_equal:valid_from',
            'target_quantity'  => 'required|numeric|min:0',
            'unit_price'       => 'required|numeric|min:0',
            'currency_code'    => 'nullable|string|size:3',
            'unit_of_measure'  => 'nullable|string|max:20',
            'delivery_days'    => 'nullable|integer|min:0',
            'notes'            => 'nullable|string',
        ]);

        $agreement = $this->service->create(
            (int) Auth::user()->organization_id,
            $validated
        );

        return $this->created($agreement->load(['vendor', 'product']), 'Scheduling agreement created.');
    }

    public function show(string $id): JsonResponse
    {
        $agreement = SchedulingAgreement::where('organization_id', Auth::user()->organization_id)
            ->with(['vendor', 'product', 'schedules'])
            ->findOrFail($id);

        return $this->success($agreement, 'Scheduling agreement retrieved.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $agreement = SchedulingAgreement::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'valid_from'     => 'sometimes|date',
            'valid_to'       => 'nullable|date|after_or_equal:valid_from',
            'target_quantity' => 'sometimes|numeric|min:0',
            'unit_price'     => 'sometimes|numeric|min:0',
            'currency_code'  => 'nullable|string|size:3',
            'unit_of_measure' => 'nullable|string|max:20',
            'delivery_days'  => 'nullable|integer|min:0',
            'notes'          => 'nullable|string',
            'status'         => 'nullable|in:draft,active,expired,cancelled',
        ]);

        $updated = $this->service->update($agreement, $validated);

        return $this->success($updated, 'Scheduling agreement updated.');
    }

    public function destroy(string $id): JsonResponse
    {
        $agreement = SchedulingAgreement::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $agreement->delete();

        return $this->success(null, 'Scheduling agreement deleted.');
    }

    public function addSchedule(Request $request, string $id): JsonResponse
    {
        $agreement = SchedulingAgreement::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'schedule_date'      => 'required|date',
            'scheduled_quantity' => 'required|numeric|min:0',
        ]);

        $line = $this->service->addScheduleLine($agreement, $validated);

        return $this->created($line, 'Schedule line added.');
    }

    public function updateSchedule(Request $request, string $id, string $lineId): JsonResponse
    {
        $agreement = SchedulingAgreement::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $line = SaDeliverySchedule::where('scheduling_agreement_id', $agreement->id)
            ->findOrFail($lineId);

        $validated = $request->validate([
            'schedule_date'      => 'sometimes|date',
            'scheduled_quantity' => 'sometimes|numeric|min:0',
            'status'             => 'nullable|in:open,partial,complete,cancelled',
        ]);

        $updated = $this->service->updateScheduleLine($line, $validated);

        return $this->success($updated, 'Schedule line updated.');
    }

    public function receiveDelivery(Request $request, string $id, string $lineId): JsonResponse
    {
        $agreement = SchedulingAgreement::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $line = SaDeliverySchedule::where('scheduling_agreement_id', $agreement->id)
            ->findOrFail($lineId);

        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        $this->service->receiveDelivery($line, (float) $validated['quantity']);

        return $this->success($line->fresh(), 'Delivery received.');
    }

    public function getSchedules(string $id): JsonResponse
    {
        $agreement = SchedulingAgreement::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $schedules = $agreement->schedules()->orderBy('schedule_date')->get();

        return $this->success($schedules, 'Schedules retrieved.');
    }
}
