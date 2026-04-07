<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fraud;

use App\Http\Controllers\Controller;
use App\Models\Fraud\FraudAlert;
use App\Models\Fraud\FraudRule;
use App\Services\Fraud\FraudRuleTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FraudAlertController extends Controller
{
    // -------------------------------------------------------------------------
    // Alerts
    // -------------------------------------------------------------------------

    /**
     * Paginated list of fraud alerts for the authenticated organization.
     * Filterable by status, severity, and entity_type.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FraudAlert::with(['rule', 'user', 'reviewer'])
            ->where('organization_id', Auth::user()->organization_id)
            ->orderByDesc('created_at')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('severity'), fn($q) => $q->where('severity', $request->input('severity')))
            ->when($request->filled('entity_type'), fn($q) => $q->where('entity_type', $request->input('entity_type')))
            ->when($request->filled('from_date'), fn($q) => $q->where('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn($q) => $q->where('created_at', '<=', $request->input('to_date')));

        $alerts = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($alerts);
    }

    /**
     * Show a single fraud alert with full evidence.
     */
    public function show(int $id): JsonResponse
    {
        $alert = FraudAlert::with(['rule', 'user', 'reviewer'])
            ->where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        return $this->success($alert);
    }

    /**
     * Update the status of a fraud alert (reviewing / resolved / false_positive).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status'  => 'required|in:reviewing,resolved,false_positive',
            'notes'   => 'nullable|string|max:2000',
        ]);

        $alert = FraudAlert::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $alert->fill([
            'status'          => $validated['status'],
            'reviewer_notes'  => $validated['notes'] ?? null,
            'reviewed_by'     => Auth::id(),
            'reviewed_at'     => now(),
        ]);

        $alert->save();

        return $this->success($alert->fresh(['rule', 'reviewer']), 'Alert status updated.');
    }

    // -------------------------------------------------------------------------
    // Rules
    // -------------------------------------------------------------------------

    /**
     * List all fraud rules for the organization.
     */
    public function rules(Request $request): JsonResponse
    {
        $query = FraudRule::where('organization_id', Auth::user()->organization_id)
            ->orderBy('name')
            ->when($request->filled('rule_type'), fn($q) => $q->where('rule_type', $request->input('rule_type')))
            ->when($request->filled('entity_type'), fn($q) => $q->where('entity_type', $request->input('entity_type')))
            ->when($request->boolean('active_only'), fn($q) => $q->active());

        $rules = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($rules);
    }

    /**
     * Create a new fraud rule.
     */
    public function storeRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:200',
            'rule_type'    => 'required|in:velocity,amount,geographic,behavioral,pattern',
            'entity_type'  => 'required|in:invoice,payment,login,contact',
            'conditions'   => 'required|array',
            'severity'     => 'required|in:low,medium,high,critical',
            'is_active'    => 'boolean',
            'auto_block'   => 'boolean',
            'score_impact' => 'integer|min:1|max:100',
        ]);

        $rule = FraudRule::create(array_merge($validated, [
            'organization_id' => Auth::user()->organization_id,
            'created_by'      => Auth::id(),
        ]));

        return $this->created($rule, 'Fraud rule created.');
    }

    /**
     * Toggle a fraud rule on/off.
     */
    public function toggleRule(int $id): JsonResponse
    {
        $rule = FraudRule::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $rule->update(['is_active' => !$rule->is_active]);

        $state = $rule->is_active ? 'enabled' : 'disabled';

        return $this->success($rule->fresh(), "Fraud rule {$state}.");
    }

    /**
     * Seed default fraud rule templates into the organization.
     */
    public function seedDefaults(): JsonResponse
    {
        $organizationId = Auth::user()->organization_id;
        $userId         = Auth::id();
        $created        = 0;

        foreach (FraudRuleTemplates::defaults() as $template) {
            $exists = FraudRule::where('organization_id', $organizationId)
                ->where('name', $template['name'])
                ->exists();

            if (!$exists) {
                FraudRule::create(array_merge($template, [
                    'organization_id' => $organizationId,
                    'created_by'      => $userId,
                ]));

                ++$created;
            }
        }

        return $this->success(['created' => $created], "{$created} default rules seeded.");
    }
}
