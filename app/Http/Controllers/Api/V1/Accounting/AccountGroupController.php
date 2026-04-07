<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AccountGroup::query()
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->when($request->get('account_category'), fn ($q, $cat) => $q->where('account_category', $cat))
            ->orderBy('code');

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'code'                   => ['required', 'string', 'max:10', Rule::unique('accounting_account_groups')->where('organization_id', $orgId)],
            'name'                   => 'required|string|max:100',
            'account_category'       => 'required|in:balance_sheet,profit_loss,statistical',
            'number_range_from'      => 'nullable|string|max:20',
            'number_range_to'        => 'nullable|string|max:20',
            'reconciliation_account' => 'nullable|boolean',
            'reconciliation_type'    => 'nullable|in:customer,vendor,asset,none',
            'is_active'              => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $orgId;

        $accountGroup = AccountGroup::create($validated);

        return $this->created($accountGroup, 'Account group created.');
    }

    public function show(AccountGroup $accountGroup): JsonResponse
    {
        return $this->success($accountGroup);
    }

    public function update(Request $request, AccountGroup $accountGroup): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'code'                   => ['sometimes', 'required', 'string', 'max:10', Rule::unique('accounting_account_groups')->where('organization_id', $orgId)->ignore($accountGroup->id)],
            'name'                   => 'sometimes|required|string|max:100',
            'account_category'       => 'sometimes|required|in:balance_sheet,profit_loss,statistical',
            'number_range_from'      => 'nullable|string|max:20',
            'number_range_to'        => 'nullable|string|max:20',
            'reconciliation_account' => 'nullable|boolean',
            'reconciliation_type'    => 'nullable|in:customer,vendor,asset,none',
            'is_active'              => 'nullable|boolean',
        ]);

        $accountGroup->update($validated);

        return $this->success($accountGroup->fresh(), 'Account group updated.');
    }

    public function destroy(AccountGroup $accountGroup): JsonResponse
    {
        $accountGroup->delete();

        return $this->success(null, 'Account group deleted.');
    }
}
