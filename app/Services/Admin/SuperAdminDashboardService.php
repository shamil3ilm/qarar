<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SuperAdminDashboardService
{
    /**
     * Full platform overview for super admins.
     */
    public function getOverview(): array
    {
        return [
            'organizations' => $this->getOrganizationStats(),
            'users' => $this->getUserStats(),
            'revenue' => $this->getRevenueStats(),
            'usage' => $this->getUsageStats(),
            'support' => $this->getSupportStats(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Organization statistics across the platform.
     */
    public function getOrganizationStats(): array
    {
        $total = Organization::count();
        $active = Organization::where('status', 'active')->count();
        $trial = Organization::where('status', 'trial')->count();
        $suspended = Organization::where('status', 'suspended')->count();

        $newThisMonth = Organization::where('created_at', '>=', Carbon::now()->startOfMonth())->count();
        $newLastMonth = Organization::whereBetween('created_at', [
            Carbon::now()->subMonth()->startOfMonth(),
            Carbon::now()->subMonth()->endOfMonth(),
        ])->count();

        $growthRate = $newLastMonth > 0
            ? round((($newThisMonth - $newLastMonth) / $newLastMonth) * 100, 1)
            : ($newThisMonth > 0 ? 100 : 0);

        $byCountry = Organization::select('country_code', DB::raw('count(*) as count'))
            ->groupBy('country_code')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'country_code')
            ->toArray();

        return [
            'total' => $total,
            'active' => $active,
            'trial' => $trial,
            'suspended' => $suspended,
            'new_this_month' => $newThisMonth,
            'new_last_month' => $newLastMonth,
            'growth_rate' => $growthRate,
            'by_country' => $byCountry,
        ];
    }

    /**
     * User statistics across all organizations.
     */
    public function getUserStats(): array
    {
        $total = User::count();
        $active = User::where('is_active', true)->count();
        $activeToday = User::where('last_active_at', '>=', Carbon::today())->count();
        $activeThisWeek = User::where('last_active_at', '>=', Carbon::now()->subWeek())->count();

        $newThisMonth = User::where('created_at', '>=', Carbon::now()->startOfMonth())->count();

        // Users per organization distribution
        $avgUsersPerOrg = Organization::where('status', 'active')->count() > 0
            ? round($active / max(Organization::where('status', 'active')->count(), 1), 1)
            : 0;

        return [
            'total' => $total,
            'active' => $active,
            'active_today' => $activeToday,
            'active_this_week' => $activeThisWeek,
            'new_this_month' => $newThisMonth,
            'avg_per_organization' => $avgUsersPerOrg,
        ];
    }

    /**
     * Platform revenue statistics.
     */
    public function getRevenueStats(): array
    {
        $billingInvoicesTable = 'billing_invoices';

        // Check if billing tables exist
        if (!DB::getSchemaBuilder()->hasTable($billingInvoicesTable)) {
            return [
                'mrr' => 0,
                'arr' => 0,
                'this_month' => 0,
                'last_month' => 0,
                'growth_rate' => 0,
                'outstanding' => 0,
                'message' => 'Billing module not yet active',
            ];
        }

        $thisMonth = DB::table($billingInvoicesTable)
            ->where('status', 'paid')
            ->where('paid_at', '>=', Carbon::now()->startOfMonth())
            ->sum('total');

        $lastMonth = DB::table($billingInvoicesTable)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
            ])
            ->sum('total');

        $outstanding = DB::table($billingInvoicesTable)
            ->whereIn('status', ['pending', 'overdue'])
            ->sum('total');

        $growthRate = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : ($thisMonth > 0 ? 100 : 0);

        return [
            'mrr' => (float) $thisMonth,
            'arr' => (float) ($thisMonth * 12),
            'this_month' => (float) $thisMonth,
            'last_month' => (float) $lastMonth,
            'growth_rate' => $growthRate,
            'outstanding' => (float) $outstanding,
        ];
    }

    /**
     * Platform usage statistics.
     */
    public function getUsageStats(): array
    {
        $apiRequestsTable = 'api_request_logs';

        $apiRequests = 0;
        if (DB::getSchemaBuilder()->hasTable($apiRequestsTable)) {
            $apiRequests = DB::table($apiRequestsTable)
                ->where('created_at', '>=', Carbon::today())
                ->count();
        }

        return [
            'api_requests_today' => $apiRequests,
            'total_invoices_created' => DB::table('invoices')->count(),
            'total_products' => DB::table('products')->count(),
            'total_contacts' => DB::table('contacts')->count(),
        ];
    }

    /**
     * Support ticket statistics.
     */
    public function getSupportStats(): array
    {
        $supportTable = 'support_tickets';

        if (!DB::getSchemaBuilder()->hasTable($supportTable)) {
            return [
                'open' => 0,
                'pending' => 0,
                'resolved_this_week' => 0,
                'avg_resolution_hours' => 0,
                'message' => 'Support module not yet active',
            ];
        }

        $open = DB::table($supportTable)->where('status', 'open')->count();
        $pending = DB::table($supportTable)->whereIn('status', ['open', 'in_progress'])->count();
        $resolvedThisWeek = DB::table($supportTable)
            ->where('status', 'resolved')
            ->where('updated_at', '>=', Carbon::now()->subWeek())
            ->count();

        return [
            'open' => $open,
            'pending' => $pending,
            'resolved_this_week' => $resolvedThisWeek,
        ];
    }

    /**
     * Organization details for super admin drill-down.
     */
    public function getOrganizationDetail(int $organizationId): array
    {
        $org = Organization::findOrFail($organizationId);

        $userCount = User::where('organization_id', $organizationId)->count();
        $activeUsers = User::where('organization_id', $organizationId)
            ->where('last_active_at', '>=', Carbon::now()->subWeek())
            ->count();

        $invoiceCount = DB::table('invoices')
            ->where('organization_id', $organizationId)
            ->count();

        $totalRevenue = DB::table('invoices')
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', ['draft', 'voided'])
            ->sum('total');

        $productCount = DB::table('products')
            ->where('organization_id', $organizationId)
            ->count();

        return [
            'organization' => $org->toArray(),
            'metrics' => [
                'total_users' => $userCount,
                'active_users_this_week' => $activeUsers,
                'total_invoices' => $invoiceCount,
                'total_revenue' => (float) $totalRevenue,
                'total_products' => $productCount,
            ],
        ];
    }

    /**
     * User listing with activity data for super admin.
     */
    public function getUsersForOrganization(int $organizationId, int $perPage = 20): mixed
    {
        return User::where('organization_id', $organizationId)
            ->select([
                'id', 'name', 'email', 'is_active',
                'last_active_at', 'created_at',
            ])
            ->withCount(['loginHistories as login_count'])
            ->orderByDesc('last_active_at')
            ->paginate($perPage);
    }

    /**
     * Signup trend over time.
     */
    public function getSignupTrend(string $period = '6months'): array
    {
        $months = $period === '12months' ? 12 : 6;
        $labels = [];
        $orgData = [];
        $userData = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $labels[] = $date->format('M Y');

            $orgData[] = Organization::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $userData[] = User::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Organizations', 'data' => $orgData],
                ['label' => 'Users', 'data' => $userData],
            ],
        ];
    }

    /**
     * Subscription distribution.
     */
    public function getSubscriptionDistribution(): array
    {
        $subscriptionsTable = 'organization_subscriptions';

        if (!DB::getSchemaBuilder()->hasTable($subscriptionsTable)) {
            return ['labels' => [], 'data' => []];
        }

        $data = DB::table($subscriptionsTable)
            ->join('subscription_plans', 'organization_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('organization_subscriptions.status', 'active')
            ->select('subscription_plans.name', DB::raw('count(*) as count'))
            ->groupBy('subscription_plans.name')
            ->orderByDesc('count')
            ->get();

        return [
            'labels' => $data->pluck('name')->toArray(),
            'data' => $data->pluck('count')->toArray(),
        ];
    }

    /**
     * Top organizations by usage/revenue.
     */
    public function getTopOrganizations(string $sortBy = 'revenue', int $limit = 10): array
    {
        $query = Organization::where('status', 'active');

        if ($sortBy === 'revenue') {
            $orgs = $query->select('organizations.*')
                ->selectSub(
                    DB::table('invoices')
                        ->whereColumn('invoices.organization_id', 'organizations.id')
                        ->whereNotIn('status', ['draft', 'voided'])
                        ->selectRaw('COALESCE(SUM(total), 0)'),
                    'total_revenue'
                )
                ->orderByDesc('total_revenue')
                ->limit($limit)
                ->get();
        } else {
            $orgs = $query->withCount('users')
                ->orderByDesc('users_count')
                ->limit($limit)
                ->get();
        }

        return $orgs->map(fn ($org) => [
            'id' => $org->id,
            'name' => $org->name,
            'country' => $org->country_code,
            'status' => $org->status,
            'value' => $sortBy === 'revenue'
                ? (float) ($org->total_revenue ?? 0)
                : ($org->users_count ?? 0),
            'created_at' => $org->created_at->format('Y-m-d'),
        ])->toArray();
    }
}
