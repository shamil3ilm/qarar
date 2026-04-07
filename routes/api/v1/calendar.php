<?php

use App\Http\Controllers\Api\V1\Calendar\CalendarController;
use App\Http\Controllers\Api\V1\Calendar\CalendarEventController;
use App\Http\Controllers\Api\V1\Calendar\CalendarTaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Calendar API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/calendar
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Calendars
    |--------------------------------------------------------------------------
    */
    Route::prefix('calendars')->group(function () {
        Route::get('/', [CalendarController::class, 'index'])->name('calendar.calendars.index');
        Route::post('/', [CalendarController::class, 'store'])->middleware('check.permission:calendar.calendars.create')->name('calendar.calendars.store');
        Route::get('/{calendar}', [CalendarController::class, 'show'])->name('calendar.calendars.show');
        Route::put('/{calendar}', [CalendarController::class, 'update'])->name('calendar.calendars.update');
        Route::delete('/{calendar}', [CalendarController::class, 'destroy'])->name('calendar.calendars.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Calendar Events
    |--------------------------------------------------------------------------
    */
    Route::prefix('events')->group(function () {
        Route::get('/', [CalendarEventController::class, 'index'])->name('calendar.events.index');
        Route::post('/', [CalendarEventController::class, 'store'])->middleware('check.permission:calendar.events.create')->name('calendar.events.store');
        Route::get('/{calendarEvent}', [CalendarEventController::class, 'show'])->name('calendar.events.show');
        Route::put('/{calendarEvent}', [CalendarEventController::class, 'update'])->name('calendar.events.update');
        Route::delete('/{calendarEvent}', [CalendarEventController::class, 'destroy'])->name('calendar.events.destroy');

        // Attendees
        Route::post('/{calendarEvent}/attendees', [CalendarEventController::class, 'addAttendee'])->name('calendar.events.attendees.add');
        Route::delete('/{calendarEvent}/attendees/{attendee}', [CalendarEventController::class, 'removeAttendee'])->name('calendar.events.attendees.remove');
        Route::post('/{calendarEvent}/attendees/{attendee}/respond', [CalendarEventController::class, 'respondAttendee'])->name('calendar.events.attendees.respond');

        // Reminders
        Route::post('/{calendarEvent}/reminders', [CalendarEventController::class, 'setReminder'])->name('calendar.events.reminders.set');
        Route::delete('/{calendarEvent}/reminders/{reminder}', [CalendarEventController::class, 'removeReminder'])->name('calendar.events.reminders.remove');
    });

    /*
    |--------------------------------------------------------------------------
    | Calendar Tasks
    |--------------------------------------------------------------------------
    */
    Route::prefix('tasks')->group(function () {
        Route::get('/', [CalendarTaskController::class, 'index'])->name('calendar.tasks.index');
        Route::post('/', [CalendarTaskController::class, 'store'])->name('calendar.tasks.store');
        Route::get('/{task}', [CalendarTaskController::class, 'show'])->name('calendar.tasks.show');
        Route::put('/{task}', [CalendarTaskController::class, 'update'])->name('calendar.tasks.update');
        Route::delete('/{task}', [CalendarTaskController::class, 'destroy'])->name('calendar.tasks.destroy');
        Route::post('/{task}/complete', [CalendarTaskController::class, 'complete'])->name('calendar.tasks.complete');
        Route::post('/{task}/comments', [CalendarTaskController::class, 'addComment'])->name('calendar.tasks.comments.add');
        Route::get('/{task}/comments', [CalendarTaskController::class, 'comments'])->name('calendar.tasks.comments.index');
    });
});
