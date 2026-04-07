<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\IpAllowlistRule;
use App\Services\Core\IpAllowlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IpAllowlistController extends Controller
{
    public function __construct(private readonly IpAllowlistService $service) {}

    public function index(Request $request): JsonResponse
    {
        $rules = IpAllowlistRule::where('organization_id', $request->user()->organization_id)
            ->orderBy('rule_type')
            ->paginate(20);

        return $this->paginated($rules);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rule_name'      => 'required|string|max:255',
            'ip_address'     => 'nullable|ip',
            'ip_range_start' => 'nullable|ip',
            'ip_range_end'   => 'nullable|ip',
            'cidr_notation'  => 'nullable|string|max:18',
            'rule_type'      => 'required|in:allow,deny',
            'applies_to'     => 'required|in:all,api,admin,specific_role',
            'role_id'        => 'nullable|integer',
            'active'         => 'boolean',
        ]);

        $data['organization_id'] = $request->user()->organization_id;
        $data['created_by']      = $request->user()->id;

        $rule = $this->service->addRule($data);

        return $this->created($rule);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rule = IpAllowlistRule::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $data = $request->validate([
            'rule_name'  => 'sometimes|string|max:255',
            'rule_type'  => 'sometimes|in:allow,deny',
            'applies_to' => 'sometimes|in:all,api,admin,specific_role',
            'active'     => 'sometimes|boolean',
        ]);

        $rule->update($data);

        return $this->success($rule);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $rule = IpAllowlistRule::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $rule->delete();

        return $this->success(null, 'Rule deleted');
    }

    public function check(Request $request): JsonResponse
    {
        $ip     = $request->input('ip', $request->ip());
        $result = $this->service->checkAccess($ip, $request->user()->organization_id);

        return $this->success(['ip' => $ip, 'access' => $result ? 'allowed' : 'denied']);
    }
}
