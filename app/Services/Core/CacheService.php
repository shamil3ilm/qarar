<?php

declare(strict_types=1);

namespace App\Services\Core;

use Illuminate\Support\Facades\Cache;

/**
 * Structured three-tier cache service.
 *
 * Tier 1 — Static   (24 h): tax rules, currency codes, country configs, subscription plans
 * Tier 2 — Semi     (1 h):  chart of accounts, products, warehouses, settings
 * Tier 3 — Transact (5 min): account balances, stock levels, dashboard totals
 *
 * All keys are namespaced by organization to prevent cross-tenant leakage.
 * Cache busting is triggered from model observers / event listeners.
 *
 * CONTRACT:
 * - Never cache raw Eloquent models — always cache plain arrays or scalars.
 * - Always pass organization_id when the data is tenant-scoped.
 */
class CacheService
{
    public const TTL_STATIC      = 86400;   // 24 h
    public const TTL_SEMI        = 3600;    // 1 h
    public const TTL_TRANSACT    = 300;     // 5 min

    // -------------------------------------------------------------------------
    // Tier 1 — Static (config-like data, rarely changes)
    // -------------------------------------------------------------------------

    public function rememberStatic(string $key, callable $callback): mixed
    {
        return Cache::remember("erp:static:{$key}", self::TTL_STATIC, $callback);
    }

    public function forgetStatic(string $key): void
    {
        Cache::forget("erp:static:{$key}");
    }

    // -------------------------------------------------------------------------
    // Tier 2 — Semi-dynamic (master data: accounts, products, settings)
    // -------------------------------------------------------------------------

    public function rememberSemi(int $orgId, string $key, callable $callback): mixed
    {
        return Cache::remember("erp:semi:{$orgId}:{$key}", self::TTL_SEMI, $callback);
    }

    public function forgetSemi(int $orgId, string $key): void
    {
        Cache::forget("erp:semi:{$orgId}:{$key}");
    }

    /** Bust ALL semi-dynamic cache for an organization (e.g. after bulk import). */
    public function flushSemi(int $orgId): void
    {
        // Tags require a tag-capable store (Redis). Fall back to key-by-key when
        // the store does not support tags.
        if ($this->storeSupportsTagging()) {
            Cache::tags(["org:{$orgId}:semi"])->flush();
        }
        // Individual high-value keys are explicitly forgotten below.
        $this->forgetSemi($orgId, 'chart_of_accounts');
        $this->forgetSemi($orgId, 'warehouses');
        $this->forgetSemi($orgId, 'settings');
    }

    // -------------------------------------------------------------------------
    // Tier 3 — Transactional (live computed values: balances, stock, totals)
    // -------------------------------------------------------------------------

    public function rememberTransact(int $orgId, string $key, callable $callback): mixed
    {
        return Cache::remember("erp:transact:{$orgId}:{$key}", self::TTL_TRANSACT, $callback);
    }

    public function forgetTransact(int $orgId, string $key): void
    {
        Cache::forget("erp:transact:{$orgId}:{$key}");
    }

    /** Bust a specific account's balance cache. */
    public function bustAccountBalance(int $orgId, int $accountId): void
    {
        $this->forgetTransact($orgId, "account_balance:{$accountId}");
        $this->forgetTransact($orgId, 'trial_balance');
        $this->forgetTransact($orgId, 'balance_sheet');
        $this->forgetTransact($orgId, 'income_statement');
    }

    /** Bust stock cache for a product (all warehouses). */
    public function bustStockLevel(int $orgId, int $productId): void
    {
        $this->forgetTransact($orgId, "stock:{$productId}");
        $this->forgetTransact($orgId, 'reorder_list');
        $this->forgetTransact($orgId, 'dashboard_stock_summary');
    }

    /** Bust customer balance cache. */
    public function bustCustomerBalance(int $orgId, int $contactId): void
    {
        $this->forgetTransact($orgId, "customer_balance:{$contactId}");
        $this->forgetTransact($orgId, 'dashboard_ar_summary');
    }

    /** Bust dashboard cache for an organization. */
    public function bustDashboard(int $orgId): void
    {
        $this->forgetTransact($orgId, 'dashboard');
        $this->forgetTransact($orgId, 'dashboard_stock_summary');
        $this->forgetTransact($orgId, 'dashboard_ar_summary');
        $this->forgetTransact($orgId, 'dashboard_ap_summary');
    }

    // -------------------------------------------------------------------------
    // Convenience: named semi-dynamic helpers
    // -------------------------------------------------------------------------

    /** Cache the flat chart of accounts list (id, code, name, type). */
    public function chartOfAccounts(int $orgId, callable $callback): mixed
    {
        return $this->rememberSemi($orgId, 'chart_of_accounts', $callback);
    }

    /** Cache organization settings. */
    public function organizationSettings(int $orgId, callable $callback): mixed
    {
        return $this->rememberSemi($orgId, 'settings', $callback);
    }

    /** Cache warehouse list. */
    public function warehouses(int $orgId, callable $callback): mixed
    {
        return $this->rememberSemi($orgId, 'warehouses', $callback);
    }

    // -------------------------------------------------------------------------
    // Convenience: named static helpers
    // -------------------------------------------------------------------------

    /** Cache subscription plan list (changes only on admin action). */
    public function subscriptionPlans(callable $callback): mixed
    {
        return $this->rememberStatic('subscription_plans', $callback);
    }

    /** Cache country tax configurations (from StatutoryDeductionService). */
    public function statutoryConfig(string $countryCode, callable $callback): mixed
    {
        return $this->rememberStatic("statutory_config:{$countryCode}", $callback);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function storeSupportsTagging(): bool
    {
        try {
            Cache::tags(['test'])->get('test');
            return true;
        } catch (\BadMethodCallException) {
            return false;
        }
    }
}
