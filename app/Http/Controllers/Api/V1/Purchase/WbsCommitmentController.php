<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\PoWbsCommitment;
use App\Services\Purchase\WbsCommitmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WbsCommitmentController extends Controller
{
    public function __construct(
        private readonly WbsCommitmentService $commitmentService,
    ) {}

    /**
     * GET /purchase/wbs-commitments/wbs/{wbsElementId}
     * List all commitments for a WBS element.
     */
    public function forWbs(Request $request, int $wbsElementId): JsonResponse
    {
        $commitments = $this->commitmentService->getCommitmentsForWbs($wbsElementId);

        return $this->success($commitments);
    }

    /**
     * GET /purchase/wbs-commitments/wbs/{wbsElementId}/budget
     * Budget vs commitment analysis for a WBS element.
     */
    public function budgetVsCommitment(Request $request, int $wbsElementId): JsonResponse
    {
        try {
            $analysis = $this->commitmentService->getBudgetVsCommitment($wbsElementId);
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }

        return $this->success($analysis);
    }

    /**
     * POST /purchase/wbs-commitments/{id}/close
     * Manually close a commitment.
     */
    public function close(Request $request, int $id): JsonResponse
    {
        $commitment = PoWbsCommitment::where(
            'organization_id',
            $request->user()->organization_id
        )->findOrFail($id);

        if ($commitment->isClosed()) {
            return $this->success($commitment, 'Commitment is already closed.');
        }

        $commitment->update(['status' => PoWbsCommitment::STATUS_CLOSED]);

        return $this->success($commitment->fresh(), 'Commitment closed successfully.');
    }
}
