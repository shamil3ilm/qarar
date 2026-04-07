<?php

declare(strict_types=1);

namespace App\Services\Campaign;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConditionEvaluator
{
    /**
     * Evaluate all conditions against a user. All conditions must pass (AND logic).
     * Returns true if the conditions array is empty.
     */
    public function evaluate(array $conditions, User $user): bool
    {
        if (empty($conditions)) {
            return true;
        }

        try {
            foreach ($conditions as $condition) {
                if (!$this->evaluateOne($condition, $user)) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('ConditionEvaluator error', [
                'user_id'    => $user->id,
                'conditions' => $conditions,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function evaluateOne(array $condition, User $user): bool
    {
        $field    = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '=';
        $expected = $condition['value'] ?? null;

        $actual = $this->resolveField($field, $user);

        return $this->compare($actual, $operator, $expected);
    }

    private function resolveField(string $field, User $user): mixed
    {
        return match ($field) {
            'last_login_days_ago' => $user->last_login_at !== null
                ? (int) $user->last_login_at->diffInDays(now())
                : 9999,

            'account_age_days' => (int) $user->created_at->diffInDays(now()),

            'total_invoices' => (int) DB::table('invoices')
                ->where('organization_id', $user->organization_id)
                ->whereNull('deleted_at')
                ->count(),

            'total_payments' => (int) DB::table('payments_received')
                ->where('organization_id', $user->organization_id)
                ->whereNull('deleted_at')
                ->count(),

            'active_modules' => (int) DB::table('organization_modules')
                ->where('organization_id', $user->organization_id)
                ->where('is_active', true)
                ->count(),

            'has_phone' => $user->phone !== null ? 1 : 0,

            default => null,
        };
    }

    private function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '='      => $actual == $expected,
            '!='     => $actual != $expected,
            '>'      => $actual !== null && $actual > $expected,
            '<'      => $actual !== null && $actual < $expected,
            '>='     => $actual !== null && $actual >= $expected,
            '<='     => $actual !== null && $actual <= $expected,
            'in'     => is_array($expected) && in_array($actual, $expected),
            'not_in' => is_array($expected) && !in_array($actual, $expected),
            default  => false,
        };
    }
}
