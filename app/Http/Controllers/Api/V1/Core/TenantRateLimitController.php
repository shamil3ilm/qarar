<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\TenantRateLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantRateLimitController extends Controller
{
    public function __construct(private readonly TenantRateLimitService $service) {}

    public function show(Request $request): JsonResponse
    {
        $config = $this->service->getConfig($request->user()->organization_id);
        return $this->success($config);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'requests_per_minute' => 'sometimes|integer|min:1|max:600',
            'requests_per_hour'   => 'sometimes|integer|min:1|max:10000',
            'requests_per_day'    => 'sometimes|integer|min:1|max:1000000',
            'burst_limit'         => 'sometimes|integer|min:1|max:1000',
            'is_unlimited'        => 'sometimes|boolean',
            'custom_limits'       => 'nullable|array',
        ]);

        $config = $this->service->updateConfig($request->user()->organization_id, $data);

        return $this->success($config, 'Rate limit config updated');
    }

    public function stats(Request $request): JsonResponse
    {
        $stats = $this->service->getRateLimitStats($request->user()->organization_id);
        return $this->success($stats);
    }
}
