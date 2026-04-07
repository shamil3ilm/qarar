<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ParkedDocument;
use App\Services\Accounting\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParkedDocumentController extends Controller
{
    public function __construct(
        private JournalService $journalService
    ) {}

    /**
     * List parked documents.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ParkedDocument::with(['parkedBy:id,name', 'approvedBy:id,name'])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->when($request->has('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->has('document_type'), fn($q) => $q->where('document_type', $request->document_type))
            ->when($request->has('date_from'), fn($q) => $q->whereDate('document_date', '>=', $request->date_from))
            ->when($request->has('date_to'), fn($q) => $q->whereDate('document_date', '<=', $request->date_to));

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    /**
     * Park a new document.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type'  => ['required', 'string', 'max:30'],
            'reference'      => ['nullable', 'string', 'max:50'],
            'document_date'  => ['required', 'date'],
            'posting_date'   => ['required', 'date'],
            'document_data'  => ['required', 'array'],
            'total_debit'    => ['required', 'numeric', 'min:0'],
            'total_credit'   => ['required', 'numeric', 'min:0'],
            'currency_code'  => ['nullable', 'string', 'size:3'],
            'parking_reason' => ['nullable', 'string'],
        ]);

        $document = ParkedDocument::create([
            ...$validated,
            'organization_id' => $this->organizationId($request),
            'parked_by'       => auth()->id(),
            'status'          => ParkedDocument::STATUS_PARKED,
        ]);

        return $this->created($document, 'Document parked successfully.');
    }

    /**
     * Show a single parked document.
     */
    public function update(Request $request, ParkedDocument $parkedDocument): JsonResponse
    {
        if ($parkedDocument->status !== ParkedDocument::STATUS_PARKED) {
            return $this->error('Only parked documents can be updated.', 'INVALID_STATUS', 422);
        }

        $validated = $request->validate([
            'reference'      => ['nullable', 'string', 'max:50'],
            'document_date'  => ['sometimes', 'date'],
            'posting_date'   => ['sometimes', 'date'],
            'document_data'  => ['nullable', 'array'],
            'parking_reason' => ['nullable', 'string'],
        ]);

        $parkedDocument->update($validated);

        return $this->success($parkedDocument->fresh(), 'Parked document updated successfully.');
    }

    public function show(ParkedDocument $parkedDocument): JsonResponse
    {
        $parkedDocument->load(['parkedBy:id,name', 'approvedBy:id,name']);

        return $this->success($parkedDocument);
    }

    /**
     * Mark a parked document as pending approval.
     */
    public function approve(Request $request, ParkedDocument $parkedDocument): JsonResponse
    {
        if (!in_array($parkedDocument->status, [ParkedDocument::STATUS_PARKED, ParkedDocument::STATUS_PENDING_APPROVAL], true)) {
            return $this->error('Only parked or pending-approval documents can be approved.', 'INVALID_STATUS', 422);
        }

        $parkedDocument->update([
            'status'      => ParkedDocument::STATUS_PARKED,
            'approved_by' => auth()->id(),
        ]);

        return $this->success($parkedDocument->fresh(), 'Document approved for posting.');
    }

    /**
     * Post a parked document (convert to a real journal entry).
     */
    public function post(ParkedDocument $parkedDocument): JsonResponse
    {
        if (!$parkedDocument->isPostable()) {
            return $this->error('Only parked or pending-approval documents can be posted.', 'INVALID_STATUS', 422);
        }

        try {
            $journalEntry = DB::transaction(function () use ($parkedDocument) {
                $data = $parkedDocument->document_data;

                // Build journal entry lines from document_data lines key
                $lines = $data['lines'] ?? [];

                if (empty($lines)) {
                    throw new \InvalidArgumentException('Parked document has no journal lines in document_data.lines.');
                }

                $entry = $this->journalService->createEntry([
                    'organization_id' => $parkedDocument->organization_id,
                    'entry_date'      => $parkedDocument->posting_date->toDateString(),
                    'reference'       => $parkedDocument->reference,
                    'description'     => $data['description'] ?? ('Parked doc: ' . $parkedDocument->document_type),
                    'currency_code'   => $parkedDocument->currency_code,
                    'status'          => 'posted',
                    'source_type'     => ParkedDocument::class,
                    'source_id'       => $parkedDocument->id,
                ], $lines);

                $parkedDocument->update([
                    'status'      => ParkedDocument::STATUS_POSTED,
                    'approved_by' => $parkedDocument->approved_by ?? auth()->id(),
                ]);

                return $entry;
            });

            return $this->success(
                ['parked_document' => $parkedDocument->fresh(), 'journal_entry_id' => $journalEntry->id],
                'Parked document posted successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'POST_FAILED', 422);
        }
    }

    /**
     * Soft-delete a parked document.
     */
    public function destroy(ParkedDocument $parkedDocument): JsonResponse
    {
        if ($parkedDocument->status === ParkedDocument::STATUS_POSTED) {
            return $this->error('Posted documents cannot be deleted.', 'INVALID_STATUS', 422);
        }

        $parkedDocument->update(['status' => ParkedDocument::STATUS_REJECTED]);
        $parkedDocument->delete();

        return $this->success(null, 'Parked document deleted.');
    }
}
