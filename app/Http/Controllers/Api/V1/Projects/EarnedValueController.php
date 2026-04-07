<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Services\Projects\EarnedValueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarnedValueController extends Controller
{
    public function __construct(
        private EarnedValueService $evmService,
    ) {}

    /**
     * Calculate and persist an EVM snapshot for a project on a given date.
     */
    public function calculateSnapshot(Request $request, int $projectId): JsonResponse
    {
        $validated = $request->validate([
            'snapshot_date' => ['required', 'date'],
        ]);

        $snapshot = $this->evmService->calculateSnapshot($projectId, $validated['snapshot_date']);

        return $this->created($snapshot);
    }

    /**
     * Return the most recent EVM snapshot for a project.
     */
    public function latestSnapshot(int $projectId): JsonResponse
    {
        $snapshot = $this->evmService->getLatestSnapshot($projectId);

        if ($snapshot === null) {
            return $this->error('No EVM snapshots found for this project.', 404);
        }

        return $this->success($snapshot);
    }

    /**
     * Return the full EVM history for trend analysis.
     */
    public function history(int $projectId): JsonResponse
    {
        $snapshots = $this->evmService->getHistory($projectId);

        return $this->success($snapshots);
    }
}
