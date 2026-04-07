<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Calendar;

use App\Http\Controllers\Controller;
use App\Models\Calendar\Calendar;
use App\Services\Calendar\CalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(
        private CalendarService $calendarService
    ) {
    }

    /**
     * List calendars with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Calendar::with(['user'])
            ->when($request->type, fn($q, $type) => $q->ofType($type))
            ->when($request->user_id, fn($q, $id) => $q->forUser((int) $id))
            ->when($request->boolean('visible_only'), fn($q) => $q->visible())
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['name', 'created_at', 'updated_at'], 'name'),
                $this->safeSortOrder($request->sort_order, 'asc')
            );

        if ($request->per_page) {
            return $this->paginated($query->paginate((int) $request->per_page));
        }

        return $this->success($query->get());
    }

    /**
     * Store a new calendar.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
            'type' => 'nullable|in:personal,team,organization,resource',
            'is_default' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'timezone' => 'nullable|string|max:50',
        ]);

        $calendar = $this->calendarService->create($validated, auth()->id());

        return $this->created($calendar);
    }

    /**
     * Show a specific calendar.
     */
    public function show(Calendar $calendar): JsonResponse
    {
        return $this->success($calendar->load(['user', 'events']));
    }

    /**
     * Update a calendar.
     */
    public function update(Request $request, Calendar $calendar): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
            'type' => 'nullable|in:personal,team,organization,resource',
            'is_default' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'timezone' => 'nullable|string|max:50',
        ]);

        $calendar->update($validated);

        return $this->success($calendar->fresh(), 'Calendar updated successfully.');
    }

    /**
     * Delete a calendar.
     */
    public function destroy(Calendar $calendar): JsonResponse
    {
        $calendar->events()->delete();
        $calendar->delete();

        return $this->success(null, 'Calendar deleted successfully.');
    }
}
