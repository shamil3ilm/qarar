<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\GdprConsentRecord;
use App\Models\Core\GdprDataSubjectRequest;
use App\Models\Core\GdprProcessingActivity;
use App\Services\Core\GdprService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GdprController extends Controller
{
    public function __construct(private readonly GdprService $service) {}

    public function requests(Request $request): JsonResponse
    {
        $requests = GdprDataSubjectRequest::where('organization_id', $request->user()->organization_id)
            ->orderBy('received_at', 'desc')
            ->paginate(20);

        return $this->paginated($requests);
    }

    public function submitRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_type'    => 'required|in:access,erasure,portability,rectification,restriction,objection',
            'requester_name'  => 'required|string|max:255',
            'requester_email' => 'required|email',
        ]);

        $data['organization_id'] = $request->user()->organization_id;
        $data['requester_id']    = $request->user()->id;

        $dsr = $this->service->submitDataRequest($data);

        return $this->created($dsr, 'Data subject request submitted. Deadline: 30 days.');
    }

    public function processRequest(Request $request, int $id): JsonResponse
    {
        $dsr = GdprDataSubjectRequest::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        match ($dsr->request_type) {
            'erasure'     => $this->service->processErasureRequest($dsr),
            'portability' => $this->service->exportDataPortability($dsr),
            default       => $dsr->update(['status' => 'completed', 'completed_at' => now()]),
        };

        return $this->success($dsr->fresh(), 'Request processed');
    }

    public function processingRegister(Request $request): JsonResponse
    {
        $register = $this->service->getProcessingRegister($request->user()->organization_id);
        return $this->success($register);
    }

    public function storeActivity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'activity_name'          => 'required|string|max:255',
            'purpose'                => 'required|string',
            'legal_basis'            => 'required|in:consent,contract,legal_obligation,vital_interests,public_task,legitimate_interests',
            'data_categories'        => 'required|array',
            'retention_period_days'  => 'nullable|integer|min:1',
            'third_country_transfers' => 'boolean',
            'dpia_required'          => 'boolean',
        ]);

        $data['uuid']            = (string) \Illuminate\Support\Str::uuid();
        $data['organization_id'] = $request->user()->organization_id;

        $activity = GdprProcessingActivity::create($data);

        return $this->created($activity);
    }

    public function recordConsent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contact_id'   => 'nullable|integer',
            'purpose'      => 'required|string|max:255',
            'consent_text' => 'nullable|string',
        ]);

        $data['organization_id'] = $request->user()->organization_id;
        $data['ip_address']      = $request->ip();

        $record = $this->service->recordConsent($data);

        return $this->created($record);
    }

    public function withdrawConsent(Request $request, int $id): JsonResponse
    {
        $record = GdprConsentRecord::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $this->service->withdrawConsent($record);

        return $this->success($record->fresh(), 'Consent withdrawn');
    }
}
