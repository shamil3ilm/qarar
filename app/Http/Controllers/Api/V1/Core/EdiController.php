<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\EdiMessage;
use App\Models\Core\EdiPartner;
use App\Services\Core\EdiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EdiController extends Controller
{
    public function __construct(
        private EdiService $service
    ) {}

    // -------------------------------------------------------------------------
    // Partners
    // -------------------------------------------------------------------------

    public function indexPartners(Request $request): JsonResponse
    {
        $partners = $this->service->listPartners([
            ...$request->only(['partner_type', 'is_active', 'search', 'per_page']),
        ]);

        return $this->paginated($partners);
    }

    public function storePartner(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_code'          => ['required', 'string', 'max:50'],
            'partner_name'          => ['required', 'string', 'max:100'],
            'partner_type'          => ['required', 'in:vendor,customer,bank,carrier,other'],
            'edi_standard'          => ['required', 'in:edifact,x12,ubl,idoc,custom'],
            'is_active'             => ['sometimes', 'boolean'],
            'contact_id'            => ['nullable', 'exists:contacts,id'],
            'interchange_id'        => ['nullable', 'string', 'max:50'],
            'interchange_qualifier' => ['nullable', 'string', 'max:10'],
            'test_mode'             => ['sometimes', 'boolean'],
            'notes'                 => ['nullable', 'string'],
        ]);

        $partner = $this->service->createPartner([
            ...$validated,
            'organization_id' => $this->organizationId($request),
        ]);

        return $this->created($partner, 'EDI partner created successfully.');
    }

    public function showPartner(int $id): JsonResponse
    {
        $partner = EdiPartner::with('contact:id,name')->findOrFail($id);

        return $this->success($partner);
    }

    public function updatePartner(Request $request, int $id): JsonResponse
    {
        $partner = EdiPartner::findOrFail($id);

        $validated = $request->validate([
            'partner_name'          => ['sometimes', 'string', 'max:100'],
            'partner_type'          => ['sometimes', 'in:vendor,customer,bank,carrier,other'],
            'edi_standard'          => ['sometimes', 'in:edifact,x12,ubl,idoc,custom'],
            'is_active'             => ['sometimes', 'boolean'],
            'contact_id'            => ['nullable', 'exists:contacts,id'],
            'interchange_id'        => ['nullable', 'string', 'max:50'],
            'interchange_qualifier' => ['nullable', 'string', 'max:10'],
            'test_mode'             => ['sometimes', 'boolean'],
            'notes'                 => ['nullable', 'string'],
        ]);

        $partner = $this->service->updatePartner($partner, $validated);

        return $this->success($partner, 'EDI partner updated successfully.');
    }

    public function destroyPartner(int $id): JsonResponse
    {
        $partner = EdiPartner::findOrFail($id);
        $partner->delete();

        return $this->success(null, 'EDI partner deleted successfully.');
    }

    // -------------------------------------------------------------------------
    // Messages
    // -------------------------------------------------------------------------

    public function indexMessages(Request $request): JsonResponse
    {
        $messages = $this->service->listMessages([
            ...$request->only(['direction', 'status', 'partner_id', 'message_type', 'per_page']),
        ]);

        return $this->paginated($messages);
    }

    public function showMessage(int $id): JsonResponse
    {
        $message = EdiMessage::with(['partner:id,partner_code,partner_name', 'segments'])
            ->findOrFail($id);

        return $this->success($message);
    }

    /**
     * Receive an inbound EDI message.
     */
    public function receive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_id'   => ['required', 'exists:edi_partners,id'],
            'message_type' => ['required', 'string', 'max:50'],
            'raw_content'  => ['required', 'string'],
        ]);

        $message = $this->service->receiveMessage(
            (int) $validated['partner_id'],
            $validated['message_type'],
            $validated['raw_content']
        );

        return $this->created($message, 'EDI message received successfully.');
    }

    /**
     * Process a received EDI message.
     */
    public function process(int $id): JsonResponse
    {
        $message = EdiMessage::findOrFail($id);
        $message = $this->service->processMessage($message);

        return $this->success($message, 'EDI message processed successfully.');
    }

    /**
     * Send an outbound EDI message to a partner.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_id'     => ['required', 'exists:edi_partners,id'],
            'message_type'   => ['required', 'string', 'max:50'],
            'data'           => ['required', 'array'],
            'reference_type' => ['nullable', 'string', 'max:50'],
            'reference_id'   => ['nullable', 'integer'],
        ]);

        $message = $this->service->sendMessage(
            (int) $validated['partner_id'],
            $validated['message_type'],
            $validated['data']
        );

        return $this->created($message, 'EDI message sent successfully.');
    }

    /**
     * Reprocess a failed or previously processed message.
     */
    public function reprocess(int $id): JsonResponse
    {
        $message = EdiMessage::findOrFail($id);
        $message = $this->service->reprocess($message);

        return $this->success($message, 'EDI message reprocessed successfully.');
    }

    /**
     * Message history for a specific partner.
     */
    public function history(Request $request, int $partnerId): JsonResponse
    {
        EdiPartner::findOrFail($partnerId);

        $messages = $this->service->getMessageHistory($partnerId, [
            ...$request->only(['direction', 'status', 'message_type', 'per_page']),
        ]);

        return $this->paginated($messages);
    }
}
