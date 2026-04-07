<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\UserEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserEventsController extends Controller
{
    /**
     * List user events for the authenticated organisation.
     *
     * Supported query parameters:
     *   event_type  string  Filter by event type
     *   user_id     int     Filter by user
     *   from        date    Lower bound on created_at (inclusive)
     *   to          date    Upper bound on created_at (inclusive)
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        $query = UserEvent::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('created_at')
            ->when($request->filled('event_type'), fn($q) => $q->where('event_type', $request->string('event_type')))
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', (int) $request->input('user_id')))
            ->when($request->filled('from'), fn($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn($q) => $q->whereDate('created_at', '<=', $request->input('to')));

        $events = $query->paginate(50);

        return $this->success($events->items(), 'Events retrieved successfully', 200, [
            'current_page' => $events->currentPage(),
            'per_page'     => $events->perPage(),
            'total'        => $events->total(),
            'last_page'    => $events->lastPage(),
        ]);
    }

    /**
     * Return event counts grouped by type and day for the last 30 days.
     */
    public function summary(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;
        $since = now()->subDays(30);

        $rows = DB::table('user_events')
            ->select([
                'event_type',
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
            ])
            ->where('organization_id', $organizationId)
            ->where('created_at', '>=', $since)
            ->groupBy('event_type', DB::raw('DATE(created_at)'))
            ->orderByDesc('date')
            ->get();

        return $this->success($rows, 'Event summary retrieved successfully');
    }
}
