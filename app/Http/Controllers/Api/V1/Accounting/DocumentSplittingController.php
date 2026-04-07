<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\DocumentSplittingRule;
use App\Services\Accounting\DocumentSplittingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSplittingController extends Controller
{
    public function __construct(
        private readonly DocumentSplittingService $service
    ) {}

    /**
     * GET /document-splitting-rules
     * List all splitting rules for the authenticated organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $rules = DocumentSplittingRule::where('organization_id', $this->organizationId($request))
            ->ordered()
            ->paginate(50);

        return $this->success($rules);
    }

    /**
     * POST /document-splitting-rules
     * Create a new splitting rule.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:100'],
            'split_method'       => ['required', 'string', 'in:profit_center,segment,cost_center,business_area'],
            'base_item_category' => ['nullable', 'string', 'max:50'],
            'is_active'          => ['boolean'],
            'priority'           => ['integer', 'min:1'],
        ]);

        $data['organization_id'] = $this->organizationId($request);

        $rule = DocumentSplittingRule::create($data);

        return $this->success($rule, 'Document splitting rule created.', 201);
    }

    /**
     * PUT /document-splitting-rules/{rule}
     * Update an existing splitting rule.
     */
    public function update(Request $request, DocumentSplittingRule $documentSplittingRule): JsonResponse
    {
        $data = $request->validate([
            'name'               => ['sometimes', 'string', 'max:100'],
            'split_method'       => ['sometimes', 'string', 'in:profit_center,segment,cost_center,business_area'],
            'base_item_category' => ['nullable', 'string', 'max:50'],
            'is_active'          => ['boolean'],
            'priority'           => ['integer', 'min:1'],
        ]);

        $documentSplittingRule->update($data);

        return $this->success($documentSplittingRule, 'Document splitting rule updated.');
    }

    /**
     * DELETE /document-splitting-rules/{rule}
     * Remove a splitting rule.
     */
    public function destroy(DocumentSplittingRule $documentSplittingRule): JsonResponse
    {
        $documentSplittingRule->delete();

        return $this->success(null, 'Document splitting rule deleted.');
    }

    /**
     * POST /document-splitting-rules/preview
     * Simulate what the split would produce for the given journal entry data.
     *
     * Request body:
     *   organization_id  — int (optional, falls back to auth user's org)
     *   currency_code    — string (optional, default SAR)
     *   lines            — array of { debit, credit, profit_center_id?, cost_center_id?, category? }
     */
    public function splitPreview(Request $request): JsonResponse
    {
        $request->validate([
            'lines'              => ['required', 'array', 'min:1'],
            'lines.*.debit'      => ['numeric', 'min:0'],
            'lines.*.credit'     => ['numeric', 'min:0'],
            'currency_code'      => ['nullable', 'string', 'size:3'],
        ]);

        $documentData = array_merge(
            $request->only(['lines', 'currency_code']),
            ['organization_id' => $this->organizationId($request)]
        );

        $preview = $this->service->previewSplit($documentData);

        return $this->success(['split_items' => $preview]);
    }
}
