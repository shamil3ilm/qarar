<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Trade;

use App\Http\Controllers\Controller;
use App\Models\Trade\TradeDocument;
use App\Services\Trade\TradeDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeDocumentController extends Controller
{
    public function __construct(
        private TradeDocumentService $documentService
    ) {
    }

    /**
     * List trade documents.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TradeDocument::with(['contact:id,name', 'createdBy:id,name'])
            ->orderByDesc('issued_date')
            ->orderByDesc('id')
            ->when($request->has('document_type'), fn($q) => $q->forType($request->input('document_type')))
            ->when($request->has('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->has('source_type') && $request->has('source_id'), fn($q) => $q->forEntity($request->input('source_type'), $request->integer('source_id')))
            ->when($request->has('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($q) use ($search) {
                    $q->where('document_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            });

        $documents = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($documents);
    }

    /**
     * Create a trade document.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'in:bill_of_lading,airway_bill,certificate_of_origin,packing_list,commercial_invoice,insurance_cert,inspection_cert,phytosanitary,fumigation,customs_invoice,consular_invoice'],
            'document_number' => ['required', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'source_type' => ['nullable', 'string', 'max:100'],
            'source_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'issued_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'issuing_authority' => ['nullable', 'string', 'max:255'],
            'issuing_country' => ['nullable', 'string', 'max:3'],
            'status' => ['sometimes', 'in:active,expired,cancelled,draft'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $document = $this->documentService->create($validated);

        return $this->created($document);
    }

    /**
     * Show a trade document.
     */
    public function show(TradeDocument $tradeDocument): JsonResponse
    {
        $tradeDocument->load(['contact', 'createdBy:id,name']);

        return $this->success($tradeDocument);
    }

    /**
     * Update a trade document.
     */
    public function update(Request $request, TradeDocument $tradeDocument): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['sometimes', 'in:bill_of_lading,airway_bill,certificate_of_origin,packing_list,commercial_invoice,insurance_cert,inspection_cert,phytosanitary,fumigation,customs_invoice,consular_invoice'],
            'document_number' => ['sometimes', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'source_type' => ['nullable', 'string', 'max:100'],
            'source_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'issued_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'issuing_authority' => ['nullable', 'string', 'max:255'],
            'issuing_country' => ['nullable', 'string', 'max:3'],
            'status' => ['sometimes', 'in:active,expired,cancelled,draft'],
            'notes' => ['nullable', 'string'],
        ]);

        $document = $this->documentService->update($tradeDocument, $validated);

        return $this->success($document, 'Trade document updated successfully');
    }

    /**
     * Delete a trade document.
     */
    public function destroy(TradeDocument $tradeDocument): JsonResponse
    {
        $tradeDocument->delete();

        return $this->success(null, 'Trade document deleted successfully');
    }

    /**
     * Attach a file to a trade document.
     */
    public function attachFile(Request $request, TradeDocument $tradeDocument): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB max
        ]);

        $file = $request->file('file');
        $path = $file->store('trade-documents', 'private');

        $document = $this->documentService->attachFile(
            $tradeDocument,
            $path,
            $file->getMimeType(),
            $file->getSize()
        );

        return $this->success($document, 'File attached successfully');
    }
}
