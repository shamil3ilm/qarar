<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Services\Compliance\KriService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KriController extends Controller
{
    public function __construct(
        private readonly KriService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);
        $filters        = $request->only(['risk_id', 'last_status', 'is_active', 'per_page']);

        $kris = $this->service->listKris($organizationId, $filters);

        return $this->paginated($kris);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'risk_id'         => ['nullable', 'integer', 'exists:grc_risks,id'],
            'kri_code'        => ['required', 'string', 'max:30'],
            'name'            => ['required', 'string', 'max:150'],
            'description'     => ['nullable', 'string'],
            'data_source'     => ['required', 'string', 'max:100'],
            'metric_field'    => ['nullable', 'string', 'max:100'],
            'aggregation'     => ['required', 'in:count,sum,avg,max,min,percentage'],
            'threshold_green' => ['required', 'numeric'],
            'threshold_amber' => ['required', 'numeric'],
            'threshold_red'   => ['required', 'numeric'],
            'direction'       => ['required', 'in:lower_is_better,higher_is_better'],
            'frequency'       => ['required', 'in:daily,weekly,monthly,quarterly'],
            'owner_id'        => ['nullable', 'integer', 'exists:users,id'],
            'is_active'       => ['boolean'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = (int) auth()->id();

        $kri = $this->service->createKri($organizationId, $data, $userId);

        return $this->created($kri->load(['risk', 'owner']), 'KRI created');
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $organizationId = $this->organizationId($request);
        $kri            = $this->service->findKri($organizationId, $uuid);

        return $this->success($kri);
    }

    public function recordReading(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'value' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = (int) auth()->id();

        $reading = $this->service->recordReading(
            $organizationId,
            $uuid,
            (float) $data['value'],
            $userId,
            $data['notes'] ?? null,
        );

        return $this->created($reading, 'KRI reading recorded');
    }

    public function readings(Request $request, string $uuid): JsonResponse
    {
        $organizationId = $this->organizationId($request);
        $months         = $request->integer('months', 6);

        $history = $this->service->getReadings($organizationId, $uuid, max(1, min(24, $months)));

        return $this->success($history);
    }
}
