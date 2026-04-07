<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DocumentType::query()
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->orderBy('code');

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'code'                        => ['required', 'string', 'max:10', Rule::unique('accounting_document_types')->where('organization_id', $orgId)],
            'name'                        => 'required|string|max:100',
            'account_type'                => 'nullable|in:asset,liability,revenue,expense,equity,all',
            'number_range_code'           => 'nullable|string|max:20',
            'reverse_document_type'       => 'nullable|boolean',
            'reverse_document_type_code'  => 'nullable|string|max:10',
            'require_reference'           => 'nullable|boolean',
            'check_duplicate_invoice'     => 'nullable|boolean',
            'is_active'                   => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $orgId;

        $documentType = DocumentType::create($validated);

        return $this->created($documentType, 'Document type created.');
    }

    public function show(DocumentType $documentType): JsonResponse
    {
        return $this->success($documentType);
    }

    public function update(Request $request, DocumentType $documentType): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'code'                        => ['sometimes', 'required', 'string', 'max:10', Rule::unique('accounting_document_types')->where('organization_id', $orgId)->ignore($documentType->id)],
            'name'                        => 'sometimes|required|string|max:100',
            'account_type'                => 'nullable|in:asset,liability,revenue,expense,equity,all',
            'number_range_code'           => 'nullable|string|max:20',
            'reverse_document_type'       => 'nullable|boolean',
            'reverse_document_type_code'  => 'nullable|string|max:10',
            'require_reference'           => 'nullable|boolean',
            'check_duplicate_invoice'     => 'nullable|boolean',
            'is_active'                   => 'nullable|boolean',
        ]);

        $documentType->update($validated);

        return $this->success($documentType->fresh(), 'Document type updated.');
    }

    public function destroy(DocumentType $documentType): JsonResponse
    {
        $documentType->delete();

        return $this->success(null, 'Document type deleted.');
    }
}
