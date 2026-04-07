<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\ErsConfiguration;
use App\Models\Purchase\ErsRun;
use App\Services\Purchase\ErsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ErsController extends Controller
{
    public function __construct(private readonly ErsService $service) {}

    public function configs(Request $request): JsonResponse
    {
        $configs = ErsConfiguration::where('organization_id', Auth::user()->organization_id)
            ->with('vendor')
            ->paginate($request->integer('per_page', 20));

        return $this->success($configs, 'ERS configurations retrieved.');
    }

    public function saveConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id'         => 'required|integer|exists:contacts,id',
            'is_enabled'        => 'boolean',
            'auto_post'         => 'boolean',
            'tolerance_percent' => 'numeric|min:0|max:100',
        ]);

        $config = $this->service->saveConfig(
            (int) Auth::user()->organization_id,
            (int) $validated['vendor_id'],
            $validated
        );

        return $this->success($config->load('vendor'), 'ERS configuration saved.');
    }

    public function runErs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_ids'   => 'nullable|array',
            'vendor_ids.*' => 'integer|exists:contacts,id',
        ]);

        $run = $this->service->runErs(
            (int) Auth::user()->organization_id,
            $validated['vendor_ids'] ?? null
        );

        return $this->success($run, 'ERS run completed.');
    }

    public function getRuns(Request $request): JsonResponse
    {
        $runs = ErsRun::where('organization_id', Auth::user()->organization_id)
            ->orderByDesc('run_date')
            ->paginate($request->integer('per_page', 20));

        return $this->success($runs, 'ERS runs retrieved.');
    }

    public function getRunItems(string $runId): JsonResponse
    {
        $run = ErsRun::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($runId);

        $items = $run->items()->with(['goodsReceipt', 'bill', 'vendor'])->get();

        return $this->success($items, 'ERS run items retrieved.');
    }
}
