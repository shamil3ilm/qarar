<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Calendar;

use App\Http\Controllers\Controller;
use App\Models\Calendar\CalendarEvent;
use App\Models\Calendar\CalendarEventAttendee;
use App\Models\Calendar\CalendarEventReminder;
use App\Services\Calendar\CalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    public function __construct(
        private CalendarService $calendarService
    ) {
    }

    /**
     * List events with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CalendarEvent::with(['calendar', 'creator', 'attendees', 'reminders'])
            ->when($request->calendar_id, fn($q, $id) => $q->forCalendar((int) $id))
            ->when($request->event_type, fn($q, $type) => $q->ofType($type))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->start_date && $request->end_date, function ($q) use ($request) {
                $q->inDateRange($request->start_date, $request->end_date);
            })
            ->when($request->boolean('upcoming'), fn($q) => $q->upcoming())
            ->when($request->boolean('all_day'), fn($q) => $q->allDay())
            ->when($request->boolean('recurring'), fn($q) => $q->recurring())
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['title', 'start_at', 'end_at', 'created_at', 'updated_at'], 'start_at'),
                $this->safeSortOrder($request->sort_order, 'asc')
            );

        if ($request->per_page) {
            return $this->paginated($query->paginate((int) $request->per_page));
        }

        return $this->success($query->get());
    }

    /**
     * Store a new event.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'calendar_id' => 'required|exists:calendars,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'event_type' => 'nullable|in:event,meeting,task,reminder,holiday',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'is_all_day' => 'nullable|boolean',
            'timezone' => 'nullable|string|max:50',
            'status' => 'nullable|in:tentative,confirmed,cancelled',
            'visibility' => 'nullable|in:default,public,private',
            'color' => 'nullable|string|max:7',
            'related_type' => 'nullable|string',
            'related_id' => 'nullable|integer',
            'is_recurring' => 'nullable|boolean',
            'recurring_rule' => 'nullable|array',
            'recurring_rule.frequency' => 'required_with:recurring_rule|in:daily,weekly,monthly,yearly',
            'recurring_rule.interval' => 'nullable|integer|min:1|max:255',
            'recurring_rule.by_day' => 'nullable|array',
            'recurring_rule.by_month_day' => 'nullable|integer|min:1|max:31',
            'recurring_rule.by_month' => 'nullable|integer|min:1|max:12',
            'recurring_rule.until_date' => 'nullable|date',
            'recurring_rule.count' => 'nullable|integer|min:1',
            'attendees' => 'nullable|array',
            'attendees.*.user_id' => 'nullable|exists:users,id',
            'attendees.*.email' => 'nullable|email',
            'attendees.*.name' => 'nullable|string|max:255',
            'attendees.*.role' => 'nullable|in:organizer,attendee,optional',
            'reminders' => 'nullable|array',
            'reminders.*.method' => 'nullable|in:notification,email,sms',
            'reminders.*.minutes_before' => 'required_with:reminders|integer|min:0',
        ]);

        $event = $this->calendarService->createEvent($validated, auth()->id());

        return $this->created($event);
    }

    /**
     * Show a specific event.
     */
    public function show(CalendarEvent $calendarEvent): JsonResponse
    {
        return $this->success(
            $calendarEvent->load(['calendar', 'creator', 'attendees.user', 'reminders', 'recurringRule'])
        );
    }

    /**
     * Update an event.
     */
    public function update(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $validated = $request->validate([
            'calendar_id' => 'sometimes|exists:calendars,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'event_type' => 'nullable|in:event,meeting,task,reminder,holiday',
            'start_at' => 'sometimes|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'is_all_day' => 'nullable|boolean',
            'timezone' => 'nullable|string|max:50',
            'status' => 'nullable|in:tentative,confirmed,cancelled',
            'visibility' => 'nullable|in:default,public,private',
            'color' => 'nullable|string|max:7',
            'is_recurring' => 'nullable|boolean',
            'recurring_rule' => 'nullable|array',
            'recurring_rule.frequency' => 'required_with:recurring_rule|in:daily,weekly,monthly,yearly',
            'recurring_rule.interval' => 'nullable|integer|min:1|max:255',
            'recurring_rule.by_day' => 'nullable|array',
            'recurring_rule.until_date' => 'nullable|date',
            'recurring_rule.count' => 'nullable|integer|min:1',
        ]);

        $event = $this->calendarService->updateEvent($calendarEvent, $validated);

        return $this->success($event, 'Event updated successfully.');
    }

    /**
     * Delete an event.
     */
    public function destroy(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $deleteSeries = $request->boolean('delete_series');

        $this->calendarService->deleteEvent($calendarEvent, $deleteSeries);

        return $this->success(null, 'Event deleted successfully.');
    }

    /**
     * Add an attendee to an event.
     */
    public function addAttendee(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'email' => 'nullable|email|max:255',
            'name' => 'nullable|string|max:255',
            'role' => 'nullable|in:organizer,attendee,optional',
        ]);

        $attendee = $this->calendarService->addAttendee($calendarEvent, $validated);

        return $this->created($attendee->load('user'));
    }

    /**
     * Remove an attendee from an event.
     */
    public function removeAttendee(CalendarEvent $calendarEvent, CalendarEventAttendee $attendee): JsonResponse
    {
        $this->calendarService->removeAttendee($calendarEvent, $attendee->id);

        return $this->success(null, 'Attendee removed successfully.');
    }

    /**
     * Respond to an event attendance (accept/decline/tentative).
     */
    public function respondAttendee(Request $request, CalendarEvent $calendarEvent, CalendarEventAttendee $attendee): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:accepted,declined,tentative',
            'comment' => 'nullable|string|max:500',
        ]);

        $attendee->update([
            'status' => $validated['status'],
            'comment' => $validated['comment'] ?? null,
            'responded_at' => now(),
        ]);

        return $this->success($attendee->fresh('user'), 'Response recorded successfully.');
    }

    /**
     * Set a reminder for an event.
     */
    public function setReminder(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $validated = $request->validate([
            'method' => 'nullable|in:notification,email,sms',
            'minutes_before' => 'nullable|integer|min:0',
            'reminder_minutes' => 'nullable|integer|min:0',
        ]);

        // Accept both field names
        if (!isset($validated['minutes_before']) && isset($validated['reminder_minutes'])) {
            $validated['minutes_before'] = $validated['reminder_minutes'];
        }

        $validated['minutes_before'] = $validated['minutes_before'] ?? 0;

        $reminder = $this->calendarService->setReminder($calendarEvent, $validated);

        return $this->created($reminder);
    }

    /**
     * Remove a reminder from an event.
     */
    public function removeReminder(CalendarEvent $calendarEvent, CalendarEventReminder $reminder): JsonResponse
    {
        $reminder->delete();

        return $this->success(null, 'Reminder removed successfully.');
    }
}
