<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Calendar\Calendar;
use App\Models\Calendar\CalendarEvent;
use App\Models\Calendar\CalendarEventAttendee;
use App\Models\Calendar\CalendarEventReminder;
use App\Models\Calendar\CalendarRecurringRule;
use Illuminate\Support\Facades\DB;

class CalendarService
{
    /**
     * Create a new calendar.
     */
    public function create(array $data, int $userId): Calendar
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['type'] = $data['type'] ?? Calendar::TYPE_PERSONAL;
            $data['user_id'] = $data['user_id'] ?? $userId;

            $calendar = Calendar::create($data);

            return $calendar;
        });
    }

    /**
     * Get events for a date range, optionally filtered by calendar.
     */
    public function getEvents(
        ?int $calendarId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $eventType = null
    ): \Illuminate\Database\Eloquent\Collection {
        $query = CalendarEvent::with(['calendar', 'creator', 'attendees', 'reminders'])
            ->when($calendarId, fn($q, $id) => $q->forCalendar($id))
            ->when($startDate && $endDate, fn($q) => $q->inDateRange($startDate, $endDate))
            ->when($eventType, fn($q, $type) => $q->ofType($type))
            ->orderBy('start_at');

        return $query->get();
    }

    /**
     * Create a new calendar event.
     */
    public function createEvent(array $data, int $userId): CalendarEvent
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['created_by'] = $data['created_by'] ?? $userId;
            $data['status'] = $data['status'] ?? CalendarEvent::STATUS_CONFIRMED;

            $event = CalendarEvent::create($data);

            // Create recurring rule if recurring
            if (!empty($data['is_recurring']) && !empty($data['recurring_rule'])) {
                CalendarRecurringRule::create(array_merge(
                    $data['recurring_rule'],
                    ['event_id' => $event->id]
                ));
            }

            // Add attendees if provided
            if (!empty($data['attendees'])) {
                foreach ($data['attendees'] as $attendee) {
                    $attendee['event_id'] = $event->id;
                    CalendarEventAttendee::create($attendee);
                }
            }

            // Add reminders if provided
            if (!empty($data['reminders'])) {
                foreach ($data['reminders'] as $reminder) {
                    $reminder['event_id'] = $event->id;
                    CalendarEventReminder::create($reminder);
                }
            }

            return $event->load(['calendar', 'creator', 'attendees', 'reminders', 'recurringRule']);
        });
    }

    /**
     * Update a calendar event.
     */
    public function updateEvent(CalendarEvent $event, array $data): CalendarEvent
    {
        return DB::transaction(function () use ($event, $data) {
            if ($event->isCancelled()) {
                throw new \InvalidArgumentException('Cancelled events cannot be updated.');
            }

            $event->update($data);

            // Update recurring rule if provided
            if (isset($data['recurring_rule'])) {
                if ($event->recurringRule) {
                    $event->recurringRule->update($data['recurring_rule']);
                } elseif (!empty($data['is_recurring'])) {
                    CalendarRecurringRule::create(array_merge(
                        $data['recurring_rule'],
                        ['event_id' => $event->id]
                    ));
                }
            }

            return $event->fresh(['calendar', 'creator', 'attendees', 'reminders', 'recurringRule']);
        });
    }

    /**
     * Delete a calendar event.
     */
    public function deleteEvent(CalendarEvent $event, bool $deleteRecurringSeries = false): bool
    {
        return DB::transaction(function () use ($event, $deleteRecurringSeries) {
            if ($deleteRecurringSeries && $event->isRecurring()) {
                // Delete all child events in the series
                $event->childEvents()->delete();
            }

            return (bool) $event->delete();
        });
    }

    /**
     * Add an attendee to an event.
     */
    public function addAttendee(CalendarEvent $event, array $data): CalendarEventAttendee
    {
        return DB::transaction(function () use ($event, $data) {
            $data['event_id'] = $event->id;
            $data['status'] = $data['status'] ?? CalendarEventAttendee::STATUS_PENDING;

            return CalendarEventAttendee::create($data);
        });
    }

    /**
     * Remove an attendee from an event.
     */
    public function removeAttendee(CalendarEvent $event, int $attendeeId): bool
    {
        return DB::transaction(function () use ($event, $attendeeId) {
            return (bool) $event->attendees()->where('id', $attendeeId)->delete();
        });
    }

    /**
     * Set a reminder for an event.
     */
    public function setReminder(CalendarEvent $event, array $data): CalendarEventReminder
    {
        return DB::transaction(function () use ($event, $data) {
            $data['event_id'] = $event->id;
            $data['method'] = $data['method'] ?? CalendarEventReminder::METHOD_NOTIFICATION;

            return CalendarEventReminder::create($data);
        });
    }
}
