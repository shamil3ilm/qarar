<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\WebhookDlqEntry;
use App\Services\Core\WebhookDlqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookDlqController extends Controller
{
    public function __construct(private readonly WebhookDlqService $service) {}

    public function index(Request $request): JsonResponse
    {
        $entries = WebhookDlqEntry::where('organization_id', $request->user()->organization_id)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('last_failed_at', 'desc')
            ->paginate(20);

        return $this->paginated($entries);
    }

    public function summary(Request $request): JsonResponse
    {
        $summary = $this->service->getDlqSummary($request->user()->organization_id);
        return $this->success($summary);
    }

    public function replay(Request $request, int $id): JsonResponse
    {
        $entry = WebhookDlqEntry::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $this->service->replay($entry, $request->user()->id);

        return $this->success($entry->fresh(), 'Webhook event replayed');
    }

    public function bulkReplay(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $entries = WebhookDlqEntry::where('organization_id', $request->user()->organization_id)
            ->whereIn('id', $data['ids'])
            ->get();

        foreach ($entries as $entry) {
            $this->service->replay($entry, $request->user()->id);
        }

        return $this->success(['replayed' => $entries->count()], 'Bulk replay initiated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $entry = WebhookDlqEntry::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $entry->delete();

        return $this->success(null, 'DLQ entry deleted');
    }
}
