<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\CostSplittingResult;
use App\Models\Accounting\CostSplittingRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CostSplittingService
{
    // ----------------------------------------------------------------
    // CRUD
    // ----------------------------------------------------------------

    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CostSplittingRule::with(['costCenter', 'costElement'])->orderBy('id', 'desc');

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['cost_center_id'])) {
            $query->where('cost_center_id', $filters['cost_center_id']);
        }

        return $query->paginate($perPage);
    }

    public function create(array $data): CostSplittingRule
    {
        return DB::transaction(function () use ($data): CostSplittingRule {
            $this->validatePercentages(
                (float) $data['fixed_percentage'],
                (float) $data['variable_percentage']
            );

            return CostSplittingRule::create($data);
        });
    }

    public function update(CostSplittingRule $rule, array $data): CostSplittingRule
    {
        return DB::transaction(function () use ($rule, $data): CostSplittingRule {
            $fixed    = isset($data['fixed_percentage'])    ? (float) $data['fixed_percentage']    : (float) $rule->fixed_percentage;
            $variable = isset($data['variable_percentage']) ? (float) $data['variable_percentage'] : (float) $rule->variable_percentage;

            $this->validatePercentages($fixed, $variable);

            $rule->update($data);

            return $rule->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Splitting run
    // ----------------------------------------------------------------

    /**
     * Iterate all active rules for the org and create cost splitting results.
     *
     * @return array{period: int, fiscal_year: int, rules_processed: int, results: CostSplittingResult[]}
     */
    public function runSplitting(int $period, int $year, int $orgId): array
    {
        return DB::transaction(function () use ($period, $year, $orgId): array {
            $rules = CostSplittingRule::withoutGlobalScope('organization')
                ->where('organization_id', $orgId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->get();

            $results = [];

            foreach ($rules as $rule) {
                // Resolve total cost from journal entries tagged to this cost center in the period
                $totalCost = (float) DB::table('journal_entry_lines as jel')
                    ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                    ->where('je.organization_id', $orgId)
                    ->where('jel.cost_center_id', $rule->cost_center_id)
                    ->when($rule->cost_element_id, fn ($q) => $q->where('jel.cost_element_id', $rule->cost_element_id))
                    ->sum('jel.debit');

                $fixedCost    = round($totalCost * ((float) $rule->fixed_percentage / 100), 4);
                $variableCost = round($totalCost - $fixedCost, 4);

                $result = CostSplittingResult::create([
                    'organization_id'        => $orgId,
                    'cost_splitting_rule_id' => $rule->id,
                    'period'                 => $period,
                    'fiscal_year'            => $year,
                    'total_cost'             => $totalCost,
                    'fixed_cost'             => $fixedCost,
                    'variable_cost'          => $variableCost,
                    'run_at'                 => now(),
                ]);

                $results[] = $result;
            }

            return [
                'period'          => $period,
                'fiscal_year'     => $year,
                'rules_processed' => count($results),
                'results'         => $results,
            ];
        });
    }

    public function getResults(int $period, int $year): Collection
    {
        return CostSplittingResult::with('rule.costCenter')
            ->where('period', $period)
            ->where('fiscal_year', $year)
            ->orderBy('id')
            ->get();
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function validatePercentages(float $fixed, float $variable): void
    {
        if (abs($fixed + $variable - 100.0) > 0.01) {
            throw new InvalidArgumentException(
                'fixed_percentage and variable_percentage must sum to 100.'
            );
        }
    }
}
