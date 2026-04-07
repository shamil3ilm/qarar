<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\DpsListEntry;
use App\Models\Compliance\DpsSanctionList;
use App\Models\Compliance\DpsScreeningRun;
use App\Services\Compliance\DeniedPartyScreeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeniedPartyScreeningController extends Controller
{
    public function __construct(
        private readonly DeniedPartyScreeningService $dpsService
    ) {}

    // -------------------------------------------------------------------------
    // Sanction Lists
    // -------------------------------------------------------------------------

    public function lists(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $lists = DpsSanctionList::where('organization_id', $orgId)
            ->withCount(['entries' => fn ($q) => $q->where('is_active', true)])
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($lists);
    }

    public function storeList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'list_name'      => 'required|string|max:100',
            'list_authority' => 'required|in:OFAC,EU,UN,HMT,local,other',
            'list_type'      => 'required|in:denied_party,embargo,debarred',
            'is_active'      => 'nullable|boolean',
            'auto_sync'      => 'nullable|boolean',
            'sync_url'       => 'nullable|url|max:255',
        ]);

        $list = DpsSanctionList::create(array_merge(
            $validated,
            ['organization_id' => $this->organizationId($request)]
        ));

        return $this->created($list);
    }

    public function showList(int $id): JsonResponse
    {
        $list = DpsSanctionList::withCount([
            'entries' => fn ($q) => $q->where('is_active', true),
        ])->findOrFail($id);

        return $this->success($list);
    }

    public function updateList(Request $request, int $id): JsonResponse
    {
        $list      = DpsSanctionList::findOrFail($id);
        $validated = $request->validate([
            'list_name'      => 'sometimes|string|max:100',
            'list_authority' => 'sometimes|in:OFAC,EU,UN,HMT,local,other',
            'list_type'      => 'sometimes|in:denied_party,embargo,debarred',
            'is_active'      => 'nullable|boolean',
            'auto_sync'      => 'nullable|boolean',
            'sync_url'       => 'nullable|url|max:255',
        ]);

        $list->update($validated);

        return $this->success($list->fresh());
    }

    // -------------------------------------------------------------------------
    // List Entries
    // -------------------------------------------------------------------------

    public function listEntries(Request $request, int $listId): JsonResponse
    {
        DpsSanctionList::findOrFail($listId); // guard

        $entries = DpsListEntry::where('dps_sanction_list_id', $listId)
            ->when($request->input('search'), function ($q, $search): void {
                $q->where('name', 'like', "%{$search}%");
            })
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($entries);
    }

    public function storeEntry(Request $request, int $listId): JsonResponse
    {
        DpsSanctionList::findOrFail($listId); // guard

        $validated = $request->validate([
            'entry_type'     => 'required|in:person,entity,vessel,aircraft',
            'name'           => 'required|string|max:200',
            'aliases'        => 'nullable|array',
            'aliases.*'      => 'string|max:200',
            'country_code'   => 'nullable|string|size:3',
            'address'        => 'nullable|string|max:300',
            'id_number'      => 'nullable|string|max:100',
            'program'        => 'nullable|string|max:100',
            'remarks'        => 'nullable|string',
            'effective_date' => 'nullable|date',
            'expiry_date'    => 'nullable|date|after_or_equal:effective_date',
            'is_active'      => 'nullable|boolean',
        ]);

        $entry = DpsListEntry::create(array_merge(
            $validated,
            ['dps_sanction_list_id' => $listId]
        ));

        return $this->created($entry);
    }

    public function importEntries(Request $request, int $listId): JsonResponse
    {
        DpsSanctionList::findOrFail($listId); // guard

        $validated = $request->validate([
            'entries'                => 'required|array|min:1|max:5000',
            'entries.*.entry_type'   => 'nullable|in:person,entity,vessel,aircraft',
            'entries.*.name'         => 'required|string|max:200',
            'entries.*.aliases'      => 'nullable|array',
            'entries.*.country_code' => 'nullable|string|max:3',
            'entries.*.id_number'    => 'nullable|string|max:100',
        ]);

        $count = $this->dpsService->importListEntries($listId, $validated['entries']);

        return $this->success(['imported' => $count], "{$count} entries imported.");
    }

    // -------------------------------------------------------------------------
    // Screening
    // -------------------------------------------------------------------------

    public function screenContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|integer|exists:contacts,id',
            'threshold'  => 'nullable|numeric|min:0|max:100',
        ]);

        $run = $this->dpsService->screenContact(
            $validated['contact_id'],
            (float) ($validated['threshold'] ?? 80.0)
        );

        return $this->success($run->load('results.listEntry'));
    }

    public function screenAll(Request $request): JsonResponse
    {
        $orgId   = $this->organizationId($request);
        $summary = $this->dpsService->screenAll($orgId);

        return $this->success($summary, 'Bulk screening complete.');
    }

    // -------------------------------------------------------------------------
    // Screening Runs
    // -------------------------------------------------------------------------

    public function runs(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $runs = DpsScreeningRun::where('organization_id', $orgId)
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('entity_type'), fn ($q, $t) => $q->where('screened_entity_type', $t))
            ->orderByDesc('screening_date')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($runs);
    }

    public function showRun(int $id): JsonResponse
    {
        $run = DpsScreeningRun::with(['results.listEntry', 'clearedBy'])
            ->findOrFail($id);

        return $this->success($run);
    }

    public function clearRun(Request $request, int $id): JsonResponse
    {
        $run       = DpsScreeningRun::findOrFail($id);
        $validated = $request->validate([
            'notes' => 'required|string|min:5|max:1000',
        ]);

        $this->dpsService->clearScreening($run, auth()->id(), $validated['notes']);

        return $this->success($run->fresh('clearedBy'), 'Screening run cleared.');
    }

    // -------------------------------------------------------------------------
    // Pending Reviews & Status
    // -------------------------------------------------------------------------

    public function pendingReviews(Request $request): JsonResponse
    {
        $orgId   = $this->organizationId($request);
        $reviews = $this->dpsService->getPendingReviews($orgId);

        return $this->success($reviews);
    }

    public function checkContact(int $contactId): JsonResponse
    {
        $isClean = $this->dpsService->isContactClean($contactId);

        $latestRun = DpsScreeningRun::where('screened_entity_type', 'contact')
            ->where('screened_entity_id', $contactId)
            ->orderByDesc('screening_date')
            ->first();

        return $this->success([
            'contact_id'          => $contactId,
            'is_clean'            => $isClean,
            'latest_run_status'   => $latestRun?->status,
            'last_screened_at'    => $latestRun?->screening_date,
        ]);
    }
}
