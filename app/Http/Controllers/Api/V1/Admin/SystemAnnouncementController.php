<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemAnnouncement;
use App\Services\Admin\SystemAnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemAnnouncementController extends Controller
{
    public function __construct(private SystemAnnouncementService $service) {}

    public function index(Request $request): JsonResponse
    {
        $announcements = SystemAnnouncement::orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));
        return $this->paginated($announcements);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|string|in:info,warning,maintenance,feature,critical',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'show_banner' => 'nullable|boolean',
            'banner_color' => 'nullable|string|max:7',
        ]);

        $announcement = SystemAnnouncement::create($request->all());
        return $this->created($announcement);
    }

    public function show(SystemAnnouncement $announcement): JsonResponse
    {
        return $this->success($announcement);
    }

    public function update(Request $request, SystemAnnouncement $announcement): JsonResponse
    {
        $announcement->update($request->all());
        return $this->success($announcement->fresh());
    }

    public function destroy(SystemAnnouncement $announcement): JsonResponse
    {
        $announcement->delete();
        return $this->success(['message' => 'Announcement deleted']);
    }

    public function publish(SystemAnnouncement $announcement): JsonResponse
    {
        $announcement->update(['status' => 'published', 'published_at' => now()]);
        return $this->success($announcement->fresh());
    }
}
