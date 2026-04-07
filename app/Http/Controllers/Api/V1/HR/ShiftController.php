<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\HR\Shift;
use App\Services\HR\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(
        private ShiftService $shiftService
    ) {}

    /**
     * List shifts for the authenticated organization.
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $shifts = Shift::where('organization_id', $orgId)
            ->when($request->boolean('active_only', false), fn($q) => $q->active())
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($shifts);
    }

    /**
     * Create a new shift definition.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'shift_code' => 'required|string|max:30',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_minutes' => 'integer|min:0|max:480',
            'is_overnight' => 'boolean',
            'is_flexible' => 'boolean',
            'flexible_start_window_minutes' => 'nullable|integer|min:0|max:240',
            'overtime_eligible' => 'boolean',
        ]);

        $shift = Shift::create(array_merge($validated, [
            'organization_id' => auth()->user()->organization_id,
            'is_active' => true,
        ]));

        return $this->created($shift, 'Shift created successfully.');
    }

    /**
     * Update an existing shift.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $shift = Shift::where('organization_id', auth()->user()->organization_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'shift_code' => 'string|max:30',
            'start_time' => 'date_format:H:i',
            'end_time' => 'date_format:H:i',
            'break_minutes' => 'integer|min:0|max:480',
            'is_overnight' => 'boolean',
            'is_flexible' => 'boolean',
            'flexible_start_window_minutes' => 'nullable|integer|min:0|max:240',
            'overtime_eligible' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $shift->update($validated);

        return $this->success($shift->fresh(), 'Shift updated successfully.');
    }

    /**
     * Delete a shift (only if not actively assigned).
     */
    public function destroy(int $id): JsonResponse
    {
        $shift = Shift::where('organization_id', auth()->user()->organization_id)
            ->findOrFail($id);

        if ($shift->assignments()->current()->exists()) {
            return $this->error(
                'Cannot delete a shift that is currently assigned to employees.',
                'SHIFT_IN_USE',
                422
            );
        }

        $shift->delete();

        return $this->noContent();
    }

    /**
     * Assign a shift to an employee.
     */
    public function assign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $shift = Shift::findOrFail($validated['shift_id']);

        try {
            $assignment = $this->shiftService->assignShift($employee, $shift, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created($assignment->load(['employee', 'shift']), 'Shift assigned successfully.');
    }
}
