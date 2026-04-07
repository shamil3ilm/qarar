<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Leave\PublicHoliday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicHolidayController extends Controller
{
    /**
     * List public holidays.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PublicHoliday::query()
            ->when($request->year, fn($q, $year) => $q->forYear((int) $year))
            ->when($request->branch_id, fn($q, $id) => $q->forBranch((int) $id))
            ->when($request->boolean('mandatory_only'), fn($q) => $q->mandatory())
            ->orderBy('holiday_date');

        $holidays = $request->per_page
            ? $query->paginate((int) $request->per_page)
            : $query->get();

        if ($request->per_page) {
            return $this->paginated($holidays);
        }

        return $this->success($holidays);
    }

    /**
     * Create a public holiday.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
            'country_code' => 'nullable|string|max:3',
            'state_code' => 'nullable|string|max:10',
            'is_recurring' => 'nullable|boolean',
            'is_optional' => 'nullable|boolean',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $holiday = PublicHoliday::create($validated);

        return $this->created($holiday);
    }

    /**
     * Show a public holiday.
     */
    public function show(PublicHoliday $publicHoliday): JsonResponse
    {
        return $this->success($publicHoliday->load('branch'));
    }

    /**
     * Update a public holiday.
     */
    public function update(Request $request, PublicHoliday $publicHoliday): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'holiday_date' => 'sometimes|date',
            'branch_id' => 'nullable|exists:branches,id',
            'country_code' => 'nullable|string|max:3',
            'state_code' => 'nullable|string|max:10',
            'is_recurring' => 'nullable|boolean',
            'is_optional' => 'nullable|boolean',
            'year' => 'sometimes|integer|min:2000|max:2100',
        ]);

        $publicHoliday->update($validated);

        return $this->success($publicHoliday->fresh());
    }

    /**
     * Delete a public holiday.
     */
    public function destroy(PublicHoliday $publicHoliday): JsonResponse
    {
        $publicHoliday->delete();

        return $this->success(null, 'Public holiday deleted successfully.');
    }

    /**
     * Bulk create holidays.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'holidays' => 'required|array|min:1',
            'holidays.*.name' => 'required|string|max:255',
            'holidays.*.holiday_date' => 'required|date',
            'holidays.*.branch_id' => 'nullable|exists:branches,id',
            'holidays.*.country_code' => 'nullable|string|max:3',
            'holidays.*.state_code' => 'nullable|string|max:10',
            'holidays.*.is_recurring' => 'nullable|boolean',
            'holidays.*.is_optional' => 'nullable|boolean',
            'holidays.*.year' => 'required|integer|min:2000|max:2100',
        ]);

        $organizationId = $this->organizationId($request);
        $created = [];

        foreach ($validated['holidays'] as $holidayData) {
            $holidayData['organization_id'] = $organizationId;
            $created[] = PublicHoliday::create($holidayData);
        }

        return $this->created($created);
    }
}
