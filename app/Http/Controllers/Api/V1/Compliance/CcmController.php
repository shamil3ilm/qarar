<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\GrcCcmMonitor;
use App\Services\Compliance\CcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CcmController extends Controller
{
    public function __construct(
        private readonly CcmService $service
    ) {}

    public function indexMonitors(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $monitors = GrcCcmMonitor::with('owner')
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($monitors);
    }

    public function storeMonitor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'monitor_code' => ['required', 'string', 'max:50'],
            'name'         => ['required', 'string', 'max:150'],
            'description'  => ['nullable', 'string'],
            'control_type' => ['required', 'in:preventive,detective,corrective'],
            'data_source'  => ['required', 'string', 'max:100'],
            'rules'        => ['required', 'array'],
            'rules.*.field'    => ['required', 'string'],
            'rules.*.operator' => ['required', 'string'],
            'rules.*.value'    => ['nullable'],
            'rules.*.severity' => ['nullable', 'in:critical,high,medium,low'],
            'frequency'    => ['required', 'in:real_time,hourly,daily,weekly,monthly'],
            'owner_id'     => ['nullable', 'integer', 'exists:users,id'],
            'is_active'    => ['boolean'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = auth()->id();

        $monitor = $this->service->createMonitor($organizationId, $data, $userId);

        return $this->created($monitor, 'CCM monitor created');
    }

    public function runMonitor(Request $request, string $uuid): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $result = $this->service->runMonitor($organizationId, $uuid);

        return $this->success($result, 'Monitor run completed');
    }

    public function indexExceptions(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $filters = $request->only(['monitor_id', 'status', 'severity', 'detected_from', 'detected_to', 'per_page']);

        $paginator = $this->service->listExceptions($organizationId, $filters);

        return $this->paginated($paginator);
    }

    public function resolveException(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'status'           => ['required', 'in:resolved,false_positive,investigated'],
            'resolution_notes' => ['nullable', 'string'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = auth()->id();

        $exception = $this->service->resolveException($organizationId, $uuid, $data, $userId);

        return $this->success($exception, 'Exception resolved');
    }

    public function dashboard(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        return $this->success($this->service->getGrcDashboard($organizationId));
    }
}
