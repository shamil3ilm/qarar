<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\Project;
use App\Models\Projects\ProjectCostEntry;
use App\Models\Projects\ProjectSettlementRule;
use App\Models\Projects\WbsElement;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProjectSettlementService
{
    public function __construct(
        private JournalService $journalService,
    ) {}

    /**
     * Create or update a settlement rule for a project (or specific WBS element).
     *
     * @param array{
     *   project_id: int,
     *   wbs_element_id?: int|null,
     *   receiver_type: string,
     *   receiver_id: int,
     *   settlement_percentage: float
     * } $data
     */
    public function defineRule(array $data): ProjectSettlementRule
    {
        return DB::transaction(function () use ($data): ProjectSettlementRule {
            $rule = ProjectSettlementRule::create([
                'project_id'             => $data['project_id'],
                'wbs_element_id'         => $data['wbs_element_id'] ?? null,
                'receiver_type'          => $data['receiver_type'],
                'receiver_id'            => $data['receiver_id'],
                'settlement_percentage'  => $data['settlement_percentage'],
            ]);

            return $rule->fresh(['project', 'wbsElement']);
        });
    }

    /**
     * Settle project actual costs to receivers according to the defined rules.
     *
     * For each rule, the proportional share of total actual cost is posted as a
     * journal entry debit to the receiver GL account (or mapped account) and
     * credit to the project's WIP / cost-in-progress account.
     *
     * @throws RuntimeException when no settlement rules are defined for the project.
     */
    public function settle(int $projectId, string $settlementDate): array
    {
        return DB::transaction(function () use ($projectId, $settlementDate): array {
            $project = Project::findOrFail($projectId);
            $orgId   = $project->organization_id;

            $rules = ProjectSettlementRule::where('project_id', $projectId)->get();

            if ($rules->isEmpty()) {
                throw new RuntimeException(
                    "No settlement rules defined for project {$project->project_number}."
                );
            }

            // Total actual cost to settle — either from WBS element or whole project
            $totalActualCost = (float) WbsElement::where('project_id', $projectId)
                ->sum('actual_cost');

            if ($totalActualCost <= 0.0) {
                return [
                    'project_id'   => $projectId,
                    'settled_cost' => 0.0,
                    'entries'      => [],
                    'message'      => 'No actual costs to settle.',
                ];
            }

            $journalEntries = [];

            foreach ($rules as $rule) {
                $share = $totalActualCost * ((float) $rule->settlement_percentage / 100);

                if ($share <= 0.0) {
                    continue;
                }

                // Create a journal entry: Dr Receiver / Cr Project WIP
                $entry = $this->journalService->create(
                    [
                        'organization_id' => $orgId,
                        'entry_date'      => $settlementDate,
                        'reference'       => "Settlement: {$project->project_number}",
                        'description'     => "Project settlement to {$rule->receiver_type} #{$rule->receiver_id}",
                        'source_type'     => Project::class,
                        'source_id'       => $projectId,
                    ],
                    [
                        [
                            'account_id' => $rule->receiver_id,
                            'debit'      => $share,
                            'credit'     => 0,
                            'description' => "Settlement share ({$rule->settlement_percentage}%)",
                        ],
                        [
                            'account_id' => $this->getProjectWipAccountId($orgId),
                            'debit'      => 0,
                            'credit'     => $share,
                            'description' => "Project WIP clearance — {$project->project_number}",
                        ],
                    ]
                );

                $journalEntries[] = $entry->id;
            }

            // Zero out actual costs on WBS elements after settlement
            WbsElement::where('project_id', $projectId)
                ->update(['actual_cost' => 0]);

            return [
                'project_id'      => $projectId,
                'settled_cost'    => $totalActualCost,
                'settlement_date' => $settlementDate,
                'journal_entries' => $journalEntries,
            ];
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Resolve the WIP (Work-In-Progress) GL account for the organization.
     * Falls back to account code 1500 (typical WIP account).
     */
    private function getProjectWipAccountId(int $orgId): int
    {
        $account = \App\Models\Accounting\Account::where('organization_id', $orgId)
            ->where('code', '1500')
            ->first();

        return $account?->id ?? 0;
    }
}
