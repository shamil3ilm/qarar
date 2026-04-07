<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Aml;

use App\Http\Controllers\Controller;
use App\Jobs\RunAmlScreeningJob;
use App\Models\Aml\AmlRiskScore;
use App\Models\Aml\AmlSuspiciousActivity;
use App\Models\Aml\AmlTransactionFlag;
use App\Models\Sales\Contact;
use App\Services\Aml\AmlMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AmlController extends Controller
{
    public function __construct(
        private readonly AmlMonitoringService $amlService,
    ) {}

    // -------------------------------------------------------------------------
    // Risk Scores
    // -------------------------------------------------------------------------

    /**
     * List contacts by risk level.
     */
    public function riskScores(Request $request): JsonResponse
    {
        $query = AmlRiskScore::with('contact')
            ->where('organization_id', Auth::user()->organization_id)
            ->orderByDesc('score')
            ->when($request->filled('risk_level'), fn($q) => $q->where('risk_level', $request->input('risk_level')))
            ->when($request->boolean('sanctions_only'), fn($q) => $q->where('sanctions_hit', true))
            ->when($request->boolean('pep_only'), fn($q) => $q->where('pep_hit', true));

        $scores = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($scores);
    }

    /**
     * Get full risk breakdown for a single contact.
     */
    public function contactRisk(int $contactId): JsonResponse
    {
        $score = AmlRiskScore::with('contact')
            ->where('organization_id', Auth::user()->organization_id)
            ->where('contact_id', $contactId)
            ->firstOrFail();

        return $this->success($score);
    }

    // -------------------------------------------------------------------------
    // Transaction Flags
    // -------------------------------------------------------------------------

    /**
     * Paginated list of flagged transactions.
     */
    public function transactionFlags(Request $request): JsonResponse
    {
        $query = AmlTransactionFlag::with('contact')
            ->where('organization_id', Auth::user()->organization_id)
            ->orderByDesc('created_at')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('flag_reason'), fn($q) => $q->where('flag_reason', $request->input('flag_reason')))
            ->when($request->filled('transaction_type'), fn($q) => $q->where('transaction_type', $request->input('transaction_type')))
            ->when($request->filled('contact_id'), fn($q) => $q->where('contact_id', $request->integer('contact_id')));

        $flags = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($flags);
    }

    // -------------------------------------------------------------------------
    // Suspicious Activity Reports
    // -------------------------------------------------------------------------

    /**
     * List SAR records.
     */
    public function suspiciousActivities(Request $request): JsonResponse
    {
        $query = AmlSuspiciousActivity::with(['contact', 'creator'])
            ->where('organization_id', Auth::user()->organization_id)
            ->orderByDesc('created_at')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('report_type'), fn($q) => $q->where('report_type', $request->input('report_type')))
            ->when($request->filled('activity_type'), fn($q) => $q->where('activity_type', $request->input('activity_type')));

        $sars = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($sars);
    }

    /**
     * Manually create a SAR.
     */
    public function createSar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'      => 'required|integer|exists:contacts,id',
            'activity_type'   => 'required|in:structuring,smurfing,layering,unusual_pattern,sanctions_hit',
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'integer',
            'description'     => 'required|string|max:5000',
            'report_type'     => 'nullable|in:SAR,CTR,STR',
        ]);

        $sar = $this->amlService->createSar(
            organizationId: Auth::user()->organization_id,
            contactId:      $validated['contact_id'],
            activityType:   $validated['activity_type'],
            transactionIds: $validated['transaction_ids'],
            description:    $validated['description'],
            createdBy:      Auth::id(),
        );

        if (isset($validated['report_type'])) {
            $sar->update(['report_type' => $validated['report_type']]);
        }

        return $this->created($sar->load('contact', 'creator'), 'SAR created successfully.');
    }

    // -------------------------------------------------------------------------
    // Contact Screening
    // -------------------------------------------------------------------------

    /**
     * Trigger an immediate re-screening of a contact.
     */
    public function screenContact(int $contactId): JsonResponse
    {
        $contact = Contact::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($contactId);

        try {
            RunAmlScreeningJob::dispatch($contact->id, $contact->organization_id);
        } catch (\Throwable $e) {
            return $this->error('Failed to dispatch screening job: ' . $e->getMessage(), 'DISPATCH_FAILED', 500);
        }

        return $this->success(null, 'AML screening dispatched for contact.');
    }
}
