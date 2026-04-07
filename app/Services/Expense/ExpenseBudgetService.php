<?php

declare(strict_types=1);

namespace App\Services\Expense;

use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseBudget;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExpenseBudgetService
{
    /**
     * Create or update an expense budget.
     */
    public function create(array $data): ExpenseBudget
    {
        return DB::transaction(function () use ($data) {
            // Check for existing budget
            $existing = ExpenseBudget::where('organization_id', $data['organization_id'])
                ->where('category_id', $data['category_id'] ?? null)
                ->where('department_id', $data['department_id'] ?? null)
                ->where('year', $data['year'])
                ->where('month', $data['month'] ?? null)
                ->first();

            if ($existing) {
                $existing->update([
                    'budget_amount' => $data['budget_amount'],
                    'alert_at_80' => $data['alert_at_80'] ?? true,
                    'alert_at_100' => $data['alert_at_100'] ?? true,
                ]);
                return $existing->fresh();
            }

            return ExpenseBudget::create([
                'organization_id' => $data['organization_id'],
                'category_id' => $data['category_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'year' => $data['year'],
                'month' => $data['month'] ?? null,
                'budget_amount' => $data['budget_amount'],
                'alert_at_80' => $data['alert_at_80'] ?? true,
                'alert_at_100' => $data['alert_at_100'] ?? true,
            ]);
        });
    }

    /**
     * Check if an expense fits within the budget.
     */
    public function checkBudget(int $organizationId, int $categoryId, float $amount, ?string $date = null): array
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now();

        // Check monthly budget
        $monthlyBudget = ExpenseBudget::where('organization_id', $organizationId)
            ->where('category_id', $categoryId)
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->first();

        // Check annual budget
        $annualBudget = ExpenseBudget::where('organization_id', $organizationId)
            ->where('category_id', $categoryId)
            ->where('year', $date->year)
            ->whereNull('month')
            ->first();

        $result = [
            'within_budget' => true,
            'warnings' => [],
            'monthly_budget' => null,
            'annual_budget' => null,
        ];

        if ($monthlyBudget) {
            $projectedUtilization = $monthlyBudget->getTotalUtilized() + $amount;
            $percentage = $monthlyBudget->budget_amount > 0
                ? round(($projectedUtilization / (float) $monthlyBudget->budget_amount) * 100, 2)
                : 0;

            $result['monthly_budget'] = [
                'budget_amount' => (float) $monthlyBudget->budget_amount,
                'spent_amount' => (float) $monthlyBudget->spent_amount,
                'committed_amount' => (float) $monthlyBudget->committed_amount,
                'remaining' => $monthlyBudget->getRemainingBudget(),
                'projected_utilization' => $projectedUtilization,
                'projected_percentage' => $percentage,
            ];

            if ($projectedUtilization > (float) $monthlyBudget->budget_amount) {
                $result['within_budget'] = false;
                $result['warnings'][] = "Monthly budget will be exceeded by " .
                    number_format($projectedUtilization - (float) $monthlyBudget->budget_amount, 2);
            } elseif ($percentage >= 80) {
                $result['warnings'][] = "Monthly budget will be at {$percentage}% utilization";
            }
        }

        if ($annualBudget) {
            $projectedUtilization = $annualBudget->getTotalUtilized() + $amount;
            $percentage = $annualBudget->budget_amount > 0
                ? round(($projectedUtilization / (float) $annualBudget->budget_amount) * 100, 2)
                : 0;

            $result['annual_budget'] = [
                'budget_amount' => (float) $annualBudget->budget_amount,
                'spent_amount' => (float) $annualBudget->spent_amount,
                'committed_amount' => (float) $annualBudget->committed_amount,
                'remaining' => $annualBudget->getRemainingBudget(),
                'projected_utilization' => $projectedUtilization,
                'projected_percentage' => $percentage,
            ];

            if ($projectedUtilization > (float) $annualBudget->budget_amount) {
                $result['within_budget'] = false;
                $result['warnings'][] = "Annual budget will be exceeded by " .
                    number_format($projectedUtilization - (float) $annualBudget->budget_amount, 2);
            } elseif ($percentage >= 80) {
                $result['warnings'][] = "Annual budget will be at {$percentage}% utilization";
            }
        }

        return $result;
    }

    /**
     * Get budget utilization for an organization.
     */
    public function getUtilization(int $organizationId, int $year, ?int $month = null): array
    {
        $query = ExpenseBudget::where('organization_id', $organizationId)
            ->where('year', $year);

        if ($month !== null) {
            $query->where('month', $month);
        } else {
            $query->whereNull('month');
        }

        $budgets = $query->with('category:id,name,code')->get();

        $result = [
            'year' => $year,
            'month' => $month,
            'total_budget' => 0,
            'total_spent' => 0,
            'total_committed' => 0,
            'total_remaining' => 0,
            'overall_utilization' => 0,
            'categories' => [],
        ];

        foreach ($budgets as $budget) {
            $categoryData = [
                'budget_id' => $budget->id,
                'category_id' => $budget->category_id,
                'category_name' => $budget->category?->name ?? 'Uncategorized',
                'department_id' => $budget->department_id,
                'budget_amount' => (float) $budget->budget_amount,
                'spent_amount' => (float) $budget->spent_amount,
                'committed_amount' => (float) $budget->committed_amount,
                'remaining' => $budget->getRemainingBudget(),
                'utilization_percentage' => $budget->getUtilizationPercentage(),
                'is_exceeded' => $budget->isExceeded(),
            ];

            $result['categories'][] = $categoryData;
            $result['total_budget'] += (float) $budget->budget_amount;
            $result['total_spent'] += (float) $budget->spent_amount;
            $result['total_committed'] += (float) $budget->committed_amount;
        }

        $result['total_remaining'] = max(0, $result['total_budget'] - $result['total_spent'] - $result['total_committed']);
        $result['overall_utilization'] = $result['total_budget'] > 0
            ? round((($result['total_spent'] + $result['total_committed']) / $result['total_budget']) * 100, 2)
            : 0;

        return $result;
    }
}
