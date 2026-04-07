<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CoActivityConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ActivityConfirmationController extends Controller
{
    /**
     * List activity confirmations with optional filters.
     *
     * GET /controlling/activity-confirmations
     */
    public function index(Request $request): JsonResponse
    {
        $query = CoActivityConfirmation::with([
            'costCenter:id,code,name',
            'activityType:id,code,name',
            'workOrder:id,order_number',
            'confirmedBy:id,name',
        ])
            ->where('organization_id', $this->organizationId($request))
            ->orderByDesc('confirmation_date');

        $query
            ->when($request->filled('cost_center_id'), fn($q) => $q->where('cost_center_id', $request->integer('cost_center_id')))
            ->when($request->filled('activity_type_id'), fn($q) => $q->where('activity_type_id', $request->integer('activity_type_id')))
            ->when($request->filled('fiscal_year'), fn($q) => $q->where('fiscal_year', $request->integer('fiscal_year')))
            ->when($request->filled('period'), fn($q) => $q->where('period', $request->integer('period')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status));

        return $this->paginated($query->paginate($request->integer('per_page', 25)));
    }

    /**
     * Record an activity confirmation.
     * Captures actual quantity of an activity type performed at a cost centre.
     *
     * POST /controlling/activity-confirmations
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cost_center_id'     => ['required', 'integer', 'exists:cost_centers,id'],
            'activity_type_id'   => ['required', 'integer', 'exists:activity_types,id'],
            'work_order_id'      => ['nullable', 'integer', 'exists:work_orders,id'],
            'work_center_id'     => ['nullable', 'integer', 'exists:work_centers,id'],
            'confirmed_quantity' => ['required', 'numeric', 'min:0.0001'],
            'planned_quantity'   => ['nullable', 'numeric', 'min:0'],
            'uom'                => ['nullable', 'string', 'max:20'],
            'actual_rate'        => ['nullable', 'numeric', 'min:0'],
            'planned_rate'       => ['nullable', 'numeric', 'min:0'],
            'fiscal_year'        => ['required', 'integer', 'min:2000', 'max:2100'],
            'period'             => ['required', 'integer', 'min:1', 'max:12'],
            'confirmation_date'  => ['required', 'date'],
            'notes'              => ['nullable', 'string'],
        ]);

        $data['organization_id']     = $this->organizationId($request);
        $data['confirmation_number'] = 'CONF-' . strtoupper(Str::random(8));
        $data['confirmed_by']        = $request->user()->id;
        $data['status']              = CoActivityConfirmation::STATUS_CONFIRMED;

        // Derive actual_cost = confirmed_quantity * actual_rate
        if (isset($data['actual_rate'])) {
            $data['actual_cost'] = round((float) $data['confirmed_quantity'] * (float) $data['actual_rate'], 4);
        }

        $confirmation = CoActivityConfirmation::create($data);

        return $this->created(
            $confirmation->load(['costCenter:id,code,name', 'activityType:id,code,name']),
            'Activity confirmation recorded.'
        );
    }

    /**
     * Show a single activity confirmation.
     *
     * GET /controlling/activity-confirmations/{confirmation}
     */
    public function show(CoActivityConfirmation $activityConfirmation): JsonResponse
    {
        $activityConfirmation->load([
            'costCenter:id,code,name',
            'activityType:id,code,name',
            'workOrder:id,order_number',
            'workCenter:id,code,name',
            'confirmedBy:id,name',
            'reversalConfirmation:id,confirmation_number,status',
        ]);

        return $this->success($activityConfirmation);
    }

    /**
     * Reverse a confirmed activity confirmation.
     * Creates a mirror record with negative quantity and marks the original as reversed.
     *
     * POST /controlling/activity-confirmations/{confirmation}/reverse
     */
    public function reverse(Request $request, CoActivityConfirmation $activityConfirmation): JsonResponse
    {
        if (! $activityConfirmation->isConfirmed()) {
            return $this->error('Only confirmed records can be reversed.', 'ALREADY_REVERSED', 422);
        }

        $reversal = DB::transaction(function () use ($activityConfirmation, $request): CoActivityConfirmation {
            $reversal = CoActivityConfirmation::create([
                'organization_id'     => $activityConfirmation->organization_id,
                'confirmation_number' => 'REV-' . strtoupper(Str::random(8)),
                'work_order_id'       => $activityConfirmation->work_order_id,
                'work_center_id'      => $activityConfirmation->work_center_id,
                'cost_center_id'      => $activityConfirmation->cost_center_id,
                'activity_type_id'    => $activityConfirmation->activity_type_id,
                'confirmed_quantity'  => -$activityConfirmation->confirmed_quantity,
                'planned_quantity'    => $activityConfirmation->planned_quantity
                    ? -$activityConfirmation->planned_quantity
                    : null,
                'uom'                 => $activityConfirmation->uom,
                'actual_rate'         => $activityConfirmation->actual_rate,
                'planned_rate'        => $activityConfirmation->planned_rate,
                'actual_cost'         => $activityConfirmation->actual_cost
                    ? -$activityConfirmation->actual_cost
                    : null,
                'fiscal_year'         => $activityConfirmation->fiscal_year,
                'period'              => $activityConfirmation->period,
                'confirmation_date'   => now()->toDateString(),
                'confirmed_by'        => $request->user()->id,
                'status'              => CoActivityConfirmation::STATUS_CONFIRMED,
                'reversal_id'         => $activityConfirmation->id,
                'notes'               => 'Reversal of ' . $activityConfirmation->confirmation_number,
            ]);

            $activityConfirmation->update([
                'status'     => CoActivityConfirmation::STATUS_REVERSED,
                'reversal_id' => $reversal->id,
            ]);

            return $reversal;
        });

        return $this->success(
            $reversal->load(['costCenter:id,code,name', 'activityType:id,code,name']),
            'Activity confirmation reversed.'
        );
    }
}
