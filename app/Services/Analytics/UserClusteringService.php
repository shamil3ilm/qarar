<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Analytics\UserClusterAssignment;
use App\Models\Analytics\UserFeatureUsage;
use App\Models\Analytics\UserActivityLog;
use App\Models\Analytics\UserSessionExtended;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserClusteringService
{
    private const CLUSTER_RULES = [
        'power_user'        => ['api_calls_30d' => ['>=', 200], 'active_days_30d' => ['>=', 15]],
        'high_value'        => ['total_invoiced_30d' => ['>=', 100000]],
        'at_risk_churn'     => ['days_since_last_login' => ['>=', 14], 'api_calls_30d' => ['<=', 10]],
        'churned'           => ['days_since_last_login' => ['>=', 30]],
        'new_user'          => ['account_age_days' => ['<=', 7]],
        'fast_payer'        => ['payment_speed_avg_days' => ['<=', 3], 'invoice_count_30d' => ['>=', 1]],
        'slow_payer'        => ['payment_speed_avg_days' => ['>=', 15]],
        'heavy_invoicer'    => ['invoice_count_30d' => ['>=', 50]],
        'mobile_first'      => ['device_primary' => ['=', 'mobile']],
        'module_explorer'   => ['modules_used_count' => ['>=', 5]],
        'hr_focused'        => ['top_module' => ['=', 'hr']],
        'sales_focused'     => ['top_module' => ['=', 'sales']],
        'inventory_focused' => ['top_module' => ['=', 'inventory']],
        'security_conscious'=> ['two_factor_enabled' => ['=', 1]],
        'read_heavy'        => ['read_to_write_ratio' => ['>=', 10]],
        'creator'           => ['creates_per_session' => ['>=', 3]],
        'delinquent'        => ['overdue_invoice_ratio' => ['>=', 0.3]],
        'evening_user'      => ['peak_hour' => ['between', [18, 23]]],
        'morning_user'      => ['peak_hour' => ['between', [6, 10]]],
        'weekend_user'      => ['peak_day_of_week' => ['in', [5, 6]]],
    ];

    public function clusterUser(User $user): array
    {
        $dimensions = $this->getDimensions($user);
        $clusters = $this->evaluateClusters($dimensions);

        $this->persistAssignments($user, $clusters, $dimensions);

        return $clusters;
    }

    public function clusterAllUsers(int $organizationId): void
    {
        User::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    try {
                        $this->clusterUser($user);
                    } catch (\Throwable $e) {
                        Log::error('UserClusteringService: failed to cluster user', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }

    public function getDimensions(User $user): array
    {
        $now = Carbon::now();
        $thirtyDaysAgo = $now->copy()->subDays(30);

        return array_merge(
            $this->getTransactionDimensions($user, $thirtyDaysAgo),
            $this->getActivityDimensions($user, $thirtyDaysAgo),
            $this->getFeatureAdoptionDimensions($user, $thirtyDaysAgo),
            $this->getEngagementDimensions($user, $thirtyDaysAgo),
            $this->getFinancialHealthDimensions($user)
        );
    }

    private function getTransactionDimensions(User $user, Carbon $thirtyDaysAgo): array
    {
        $orgId = $user->organization_id;

        $invoiceStats = DB::table('invoices')
            ->where('organization_id', $orgId)
            ->where('created_by', $user->id)
            ->whereNull('deleted_at')
            ->selectRaw(
                'SUM(CASE WHEN created_at >= ? THEN total ELSE 0 END) as total_invoiced_30d,
                 COUNT(CASE WHEN created_at >= ? THEN 1 END) as invoice_count_30d,
                 AVG(total) as avg_invoice_amount,
                 MAX(total) as largest_single_invoice,
                 COUNT(*) as total_invoices,
                 SUM(CASE WHEN status IN (?, ?, ?) AND due_date < NOW() THEN 1 ELSE 0 END) as overdue_invoices',
                [$thirtyDaysAgo, $thirtyDaysAgo, 'sent', 'partial', 'overdue']
            )
            ->first();

        $paymentSpeed = DB::table('invoices')
            ->join('payment_allocations', 'payment_allocations.invoice_id', '=', 'invoices.id')
            ->join('payments_received', 'payments_received.id', '=', 'payment_allocations.payment_received_id')
            ->where('invoices.organization_id', $orgId)
            ->where('invoices.created_by', $user->id)
            ->whereNull('invoices.deleted_at')
            ->whereNull('payments_received.deleted_at')
            ->selectRaw('AVG(DATEDIFF(payments_received.payment_date, invoices.invoice_date)) as avg_days')
            ->value('avg_days');

        $totalInvoices = (int) ($invoiceStats->total_invoices ?? 0);
        $overdueInvoices = (int) ($invoiceStats->overdue_invoices ?? 0);

        return [
            'total_invoiced_30d' => (float) ($invoiceStats->total_invoiced_30d ?? 0),
            'invoice_count_30d' => (int) ($invoiceStats->invoice_count_30d ?? 0),
            'avg_invoice_amount' => (float) ($invoiceStats->avg_invoice_amount ?? 0),
            'payment_speed_avg_days' => $paymentSpeed !== null ? (float) $paymentSpeed : null,
            'overdue_invoice_ratio' => $totalInvoices > 0 ? round($overdueInvoices / $totalInvoices, 4) : 0.0,
            'largest_single_invoice' => (float) ($invoiceStats->largest_single_invoice ?? 0),
        ];
    }

    private function getActivityDimensions(User $user, Carbon $thirtyDaysAgo): array
    {
        $activityStats = DB::table('user_activity_logs')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw(
                'COUNT(*) as api_calls_30d,
                 COUNT(DISTINCT DATE(created_at)) as active_days_30d,
                 COUNT(DISTINCT module) as modules_used_count'
            )
            ->first();

        $avgSessionDuration = DB::table('user_sessions_extended')
            ->where('user_id', $user->id)
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds');

        $peakHour = DB::table('user_activity_logs')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as cnt')
            ->groupBy('hour')
            ->orderByDesc('cnt')
            ->value('hour');

        $peakDow = DB::table('user_activity_logs')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('DAYOFWEEK(created_at) - 2 as dow, COUNT(*) as cnt')
            ->groupBy('dow')
            ->orderByDesc('cnt')
            ->value('dow');

        $primaryDevice = DB::table('user_activity_logs')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->whereNotNull('device_type')
            ->selectRaw('device_type, COUNT(*) as cnt')
            ->groupBy('device_type')
            ->orderByDesc('cnt')
            ->value('device_type');

        return [
            'api_calls_30d' => (int) ($activityStats->api_calls_30d ?? 0),
            'active_days_30d' => (int) ($activityStats->active_days_30d ?? 0),
            'avg_session_duration_minutes' => $avgSessionDuration !== null
                ? round((float) $avgSessionDuration / 60, 2)
                : null,
            'peak_hour' => $peakHour !== null ? (int) $peakHour : null,
            'peak_day_of_week' => $peakDow !== null ? (int) $peakDow : null,
            'device_primary' => $primaryDevice,
            'modules_used_count' => (int) ($activityStats->modules_used_count ?? 0),
        ];
    }

    private function getFeatureAdoptionDimensions(User $user, Carbon $thirtyDaysAgo): array
    {
        $usageStats = DB::table('user_feature_usage')
            ->where('user_id', $user->id)
            ->where('usage_date', '>=', $thirtyDaysAgo->toDateString())
            ->selectRaw(
                'SUM(access_count) as total_access,
                 SUM(create_count) as total_creates,
                 SUM(update_count) as total_updates,
                 SUM(delete_count) as total_deletes,
                 COUNT(DISTINCT CONCAT(module, \'|\', feature)) as feature_diversity'
            )
            ->first();

        $topModule = DB::table('user_feature_usage')
            ->where('user_id', $user->id)
            ->where('usage_date', '>=', $thirtyDaysAgo->toDateString())
            ->selectRaw('module, SUM(access_count) as total')
            ->groupBy('module')
            ->orderByDesc('total')
            ->value('module');

        $sessionCount = DB::table('user_sessions_extended')
            ->where('user_id', $user->id)
            ->count();

        $totalCreates = (int) ($usageStats->total_creates ?? 0);
        $totalWrites = (int) ($usageStats->total_creates ?? 0)
            + (int) ($usageStats->total_updates ?? 0)
            + (int) ($usageStats->total_deletes ?? 0);
        $totalAccess = (int) ($usageStats->total_access ?? 0);

        return [
            'top_module' => $topModule,
            'feature_diversity' => (int) ($usageStats->feature_diversity ?? 0),
            'creates_per_session' => $sessionCount > 0
                ? round($totalCreates / $sessionCount, 2)
                : 0.0,
            'read_to_write_ratio' => $totalWrites > 0
                ? round($totalAccess / $totalWrites, 2)
                : ($totalAccess > 0 ? 999.0 : 0.0),
        ];
    }

    private function getEngagementDimensions(User $user, Carbon $thirtyDaysAgo): array
    {
        $lastLogin = $user->last_login_at ? Carbon::parse($user->last_login_at) : null;
        $daysSinceLastLogin = $lastLogin !== null
            ? (int) $lastLogin->diffInDays(Carbon::now())
            : 9999;

        $accountAgeDays = (int) Carbon::parse($user->created_at)->diffInDays(Carbon::now());

        $profileCompleteness = $this->computeProfileCompleteness($user);

        return [
            'two_factor_enabled' => $user->two_factor_enabled ? 1 : 0,
            'profile_completeness' => $profileCompleteness,
            'days_since_last_login' => $daysSinceLastLogin,
            'failed_login_ratio_30d' => 0.0,
            'account_age_days' => $accountAgeDays,
        ];
    }

    private function getFinancialHealthDimensions(User $user): array
    {
        $orgId = $user->organization_id;

        $financialStats = DB::table('invoices')
            ->where('organization_id', $orgId)
            ->where('created_by', $user->id)
            ->whereNull('deleted_at')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN amount_due ELSE 0 END) as outstanding_balance,
                 COUNT(CASE WHEN status = ? THEN 1 END) as paid_count,
                 COUNT(*) as total_count',
                ['sent', 'paid']
            )
            ->first();

        $totalCount = (int) ($financialStats->total_count ?? 0);
        $paidCount = (int) ($financialStats->paid_count ?? 0);

        return [
            'payment_reliability_score' => $totalCount > 0
                ? round($paidCount / $totalCount, 4)
                : 0.0,
            'outstanding_balance' => (float) ($financialStats->outstanding_balance ?? 0),
        ];
    }

    private function computeProfileCompleteness(User $user): int
    {
        $score = 0;

        if (!empty($user->name)) {
            $score++;
        }

        if (!empty($user->email)) {
            $score++;
        }

        if (!empty($user->phone)) {
            $score++;
        }

        if ($user->email_verified_at !== null) {
            $score++;
        }

        if ($user->two_factor_enabled) {
            $score++;
        }

        if (!empty($user->timezone)) {
            $score++;
        }

        if (!empty($user->preferred_language)) {
            $score++;
        }

        if ($user->organization_id !== null) {
            $score++;
        }

        if ($user->last_login_at !== null) {
            $score++;
        }

        if ($user->is_active) {
            $score++;
        }

        return min($score, 10);
    }

    private function evaluateClusters(array $dimensions): array
    {
        $assigned = [];

        foreach (self::CLUSTER_RULES as $clusterName => $conditions) {
            if ($this->allConditionsMet($conditions, $dimensions)) {
                $assigned[] = $clusterName;
            }
        }

        return $assigned;
    }

    private function allConditionsMet(array $conditions, array $dimensions): bool
    {
        foreach ($conditions as $dimension => $rule) {
            [$operator, $threshold] = $rule;

            $value = $dimensions[$dimension] ?? null;

            if ($value === null) {
                return false;
            }

            $met = match ($operator) {
                '>=' => $value >= $threshold,
                '<=' => $value <= $threshold,
                '>'  => $value > $threshold,
                '<'  => $value < $threshold,
                '='  => $value === $threshold,
                'between' => is_array($threshold) && $value >= $threshold[0] && $value <= $threshold[1],
                'in' => is_array($threshold) && in_array($value, $threshold, true),
                default => false,
            };

            if (!$met) {
                return false;
            }
        }

        return true;
    }

    private function persistAssignments(User $user, array $clusters, array $dimensions): void
    {
        DB::transaction(function () use ($user, $clusters, $dimensions) {
            UserClusterAssignment::where('user_id', $user->id)->delete();

            foreach ($clusters as $clusterName) {
                UserClusterAssignment::create([
                    'user_id' => $user->id,
                    'organization_id' => $user->organization_id,
                    'cluster_name' => $clusterName,
                    'algorithm' => 'rule_based',
                    'confidence' => 100,
                    'dimensions' => $dimensions,
                    'assigned_at' => now(),
                    'expires_at' => now()->addDay(),
                ]);
            }
        });
    }
}
