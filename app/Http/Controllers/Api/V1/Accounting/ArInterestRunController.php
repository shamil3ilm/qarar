<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\ArInterestRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArInterestRunController extends Controller
{
    public function __construct(
        private readonly ArInterestRunService $service
    ) {}

    /**
     * Preview interest charges without posting.
     * Returns a dry-run list of invoices and calculated interest amounts.
     *
     * GET /ar-interest-runs/preview
     */
    public function preview(Request $request): JsonResponse
    {
        $params = $request->validate([
            'annual_rate'     => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'contact_id'      => ['nullable', 'integer', 'exists:contacts,id'],
            'min_days_overdue' => ['nullable', 'integer', 'min:1'],
            'max_days_overdue' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $this->service->preview(
            $this->organizationId($request),
            $params
        );

        return $this->success($result, 'Interest preview generated.');
    }

    /**
     * Execute the interest run — post GL journal entries.
     *
     * POST /ar-interest-runs/execute
     */
    public function execute(Request $request): JsonResponse
    {
        $params = $request->validate([
            'annual_rate'      => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'contact_id'       => ['nullable', 'integer', 'exists:contacts,id'],
            'min_days_overdue' => ['nullable', 'integer', 'min:1'],
            'max_days_overdue' => ['nullable', 'integer', 'min:1'],
        ]);

        $orgId    = $this->organizationId($request);
        $branchId = $request->user()->branch_id ?? 1;

        $result = $this->service->execute($orgId, $branchId, $params, $request->user()->id);

        return $this->success($result, "Interest run complete. {$result['journal_entries_posted']} journal entries posted.");
    }
}
