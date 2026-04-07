<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\IcReconciliationItem;
use App\Models\Accounting\IcReconciliationSession;
use App\Services\Accounting\IntercompanyReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntercompanyReconciliationController extends Controller
{
    public function __construct(private readonly IntercompanyReconciliationService $service) {}

    /** GET /ic-reconciliation */
    public function index(Request $request): JsonResponse
    {
        $sessions = IcReconciliationSession::where('organization_id', $request->user()->organization_id)
            ->when($request->fiscal_year, fn ($q) => $q->where('fiscal_year', $request->fiscal_year))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate((int) $request->get('per_page', 20));

        return $this->paginatedResponse($sessions, 'IC reconciliation sessions retrieved');
    }

    /** POST /ic-reconciliation/sessions */
    public function createSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fiscal_year' => ['required', 'string', 'size:4'],
            'period'      => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $session = $this->service->createSession(
            organizationId: $request->user()->organization_id,
            fiscalYear:     $data['fiscal_year'],
            period:         (int) $data['period'],
            runByUserId:    $request->user()->id,
        );

        return $this->successResponse($session, 'Reconciliation session created', 201);
    }

    /** GET /ic-reconciliation/sessions/{session} */
    public function show(IcReconciliationSession $icReconciliationSession): JsonResponse
    {
        return $this->successResponse(
            $icReconciliationSession->load(['items', 'matches']),
            'Session retrieved',
        );
    }

    /** POST /ic-reconciliation/sessions/{session}/load-items */
    public function loadItems(Request $request, IcReconciliationSession $icReconciliationSession): JsonResponse
    {
        $data = $request->validate([
            'items'                              => ['required', 'array', 'min:1'],
            'items.*.source_type'                => ['required', 'string'],
            'items.*.source_id'                  => ['required', 'integer'],
            'items.*.reference_number'           => ['required', 'string'],
            'items.*.amount'                     => ['required', 'numeric'],
            'items.*.currency'                   => ['required', 'string', 'size:3'],
            'items.*.transaction_date'           => ['required', 'date'],
            'items.*.item_type'                  => ['required', 'in:receivable,payable'],
            'items.*.counterparty_organization_id' => ['nullable', 'integer'],
        ]);

        $this->service->loadItems($icReconciliationSession, $data['items']);

        return $this->successResponse(
            $icReconciliationSession->fresh(),
            'Items loaded into reconciliation session',
        );
    }

    /** POST /ic-reconciliation/sessions/{session}/auto-match */
    public function autoMatch(IcReconciliationSession $icReconciliationSession): JsonResponse
    {
        $result = $this->service->autoMatch($icReconciliationSession);

        return $this->successResponse($result, 'Auto-match completed');
    }

    /** POST /ic-reconciliation/sessions/{session}/manual-match */
    public function manualMatch(Request $request, IcReconciliationSession $icReconciliationSession): JsonResponse
    {
        $data = $request->validate([
            'receivable_item_id' => ['required', 'integer'],
            'payable_item_id'    => ['required', 'integer'],
            'notes'              => ['nullable', 'string'],
        ]);

        $receivable = IcReconciliationItem::findOrFail($data['receivable_item_id']);
        $payable    = IcReconciliationItem::findOrFail($data['payable_item_id']);

        $match = $this->service->manualMatch(
            session:    $icReconciliationSession,
            receivable: $receivable,
            payable:    $payable,
            notes:      $data['notes'] ?? null,
        );

        return $this->successResponse($match, 'Manual match confirmed', 201);
    }

    /** POST /ic-reconciliation/sessions/{session}/close */
    public function close(IcReconciliationSession $icReconciliationSession): JsonResponse
    {
        $session = $this->service->closeSession($icReconciliationSession);

        return $this->successResponse($session, 'Session closed');
    }
}
