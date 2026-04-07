<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\OutputConditionRecord;
use App\Models\Sales\OutputMessage;
use App\Models\Sales\OutputType;
use App\Services\Sales\OutputDeterminationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutputDeterminationController extends Controller
{
    public function __construct(
        private OutputDeterminationService $outputDeterminationService
    ) {}

    // ─────────────────────────────────────────────────────────────
    // Output Types CRUD
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/sales/output-determination/output-types
     */
    public function index(Request $request): JsonResponse
    {
        $query = OutputType::with(['conditionRecords'])
            ->latest()
            ->when($request->has('document_type'), fn($q) => $q->forDocumentType($request->string('document_type')))
            ->when($request->has('active_only'), fn($q) => $q->active());

        $types = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($types);
    }

    /**
     * POST /api/v1/sales/output-determination/output-types
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'            => 'required|string|max:10',
            'name'            => 'required|string|max:100',
            'document_type'   => 'required|in:invoice,sales_order,quotation,delivery_note,purchase_order,payment',
            'output_medium'   => 'required|in:print,email,edi,portal',
            'email_template'  => 'nullable|string|max:100',
            'print_template'  => 'nullable|string|max:100',
            'dispatch_time'   => 'required|in:immediately,on_save,on_post,scheduled',
            'is_active'       => 'nullable|boolean',
            'condition_records'                      => 'nullable|array',
            'condition_records.*.key_combination'    => 'required|in:customer,customer_group,all',
            'condition_records.*.customer_id'        => 'nullable|integer',
            'condition_records.*.customer_group_id'  => 'nullable|integer',
            'condition_records.*.valid_from'         => 'nullable|date',
            'condition_records.*.valid_to'           => 'nullable|date',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $conditionRecordsData = $validated['condition_records'] ?? [];
        unset($validated['condition_records']);

        $outputType = OutputType::create($validated);

        foreach ($conditionRecordsData as $record) {
            OutputConditionRecord::create(array_merge($record, [
                'output_type_id' => $outputType->id,
                'is_active'      => $record['is_active'] ?? true,
            ]));
        }

        return $this->success($outputType->load('conditionRecords'), 'Output type created.', 201);
    }

    /**
     * GET /api/v1/sales/output-determination/output-types/{outputType}
     */
    public function show(OutputType $outputType): JsonResponse
    {
        return $this->success($outputType->load('conditionRecords'));
    }

    /**
     * PUT /api/v1/sales/output-determination/output-types/{outputType}
     */
    public function update(Request $request, OutputType $outputType): JsonResponse
    {
        $validated = $request->validate([
            'code'           => 'sometimes|string|max:10',
            'name'           => 'sometimes|string|max:100',
            'document_type'  => 'sometimes|in:invoice,sales_order,quotation,delivery_note,purchase_order,payment',
            'output_medium'  => 'sometimes|in:print,email,edi,portal',
            'email_template' => 'nullable|string|max:100',
            'print_template' => 'nullable|string|max:100',
            'dispatch_time'  => 'sometimes|in:immediately,on_save,on_post,scheduled',
            'is_active'      => 'nullable|boolean',
        ]);

        $outputType->update($validated);

        return $this->success($outputType->fresh('conditionRecords'), 'Output type updated.');
    }

    /**
     * DELETE /api/v1/sales/output-determination/output-types/{outputType}
     */
    public function destroy(OutputType $outputType): JsonResponse
    {
        $outputType->delete();

        return $this->success(null, 'Output type deleted.');
    }

    // ─────────────────────────────────────────────────────────────
    // Output Messages
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/sales/output-determination/messages
     */
    public function messages(Request $request): JsonResponse
    {
        $query = OutputMessage::with(['outputType'])
            ->latest()
            ->when($request->has('status'), fn($q) => $q->where('status', $request->string('status')))
            ->when($request->has('document_type'), fn($q) => $q->where('document_type', $request->string('document_type')))
            ->when($request->has('document_id'), fn($q) => $q->where('document_id', $request->integer('document_id')))
            ->when($request->has('medium'), fn($q) => $q->where('medium', $request->string('medium')));

        $messages = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($messages);
    }

    /**
     * POST /api/v1/sales/output-determination/messages/{outputMessage}/retry
     */
    public function retryMessage(OutputMessage $outputMessage): JsonResponse
    {
        if (!$outputMessage->canRetry()) {
            return $this->error(
                "Output message #{$outputMessage->id} cannot be retried (status: {$outputMessage->status}).",
                422
            );
        }

        $outputMessage->update(['status' => OutputMessage::STATUS_PENDING]);

        $this->outputDeterminationService->dispatch($outputMessage);

        return $this->success($outputMessage->fresh(), 'Output message dispatched.');
    }
}
