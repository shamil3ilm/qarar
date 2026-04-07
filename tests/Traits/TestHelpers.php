<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\Currency;
use App\Models\Accounting\FiscalYear;
use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\Core\Permission;
use App\Models\Core\Role;
use App\Models\User;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

trait TestHelpers
{
    protected Organization $organization;
    protected Branch $branch;
    protected User $user;
    protected Role $role;
    protected string $token;

    protected function setUpOrganization(string $countryCode = 'SA'): void
    {
        // Seed base currencies needed by FK constraints on journal_entries, chart_of_accounts, etc.
        $this->seedBaseCurrencies();

        $this->organization = Organization::factory()->create([
            'country_code' => $countryCode,
            'tax_scheme' => $countryCode === 'IN' ? 'GST' : 'VAT',
            'base_currency' => match ($countryCode) {
                'SA' => 'SAR', 'AE' => 'AED', 'IN' => 'INR', default => 'SAR',
            },
        ]);

        $this->branch = Branch::factory()->create([
            'organization_id' => $this->organization->id,
            'country_code' => $countryCode,
            'is_default' => true,
        ]);

        // Enable all modules for test organization
        $modules = ['core', 'accounting', 'inventory', 'sales', 'purchase', 'hr', 'crm',
            'manufacturing', 'automation', 'messaging', 'expenses', 'ecommerce',
            'customs', 'trade', 'loyalty', 'billing', 'task_board'];
        foreach ($modules as $module) {
            OrganizationModule::create([
                'organization_id' => $this->organization->id,
                'module_code' => $module,
                'is_enabled' => true,
                'enabled_at' => now(),
            ]);
        }

    }

    protected function setUpAuthenticatedUser(array $permissions = []): void
    {
        if (!isset($this->organization)) {
            $this->setUpOrganization();
        }

        $this->role = Role::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Role',
            'slug' => 'test-role-' . Str::random(6),
        ]);

        if (!empty($permissions)) {
            foreach ($permissions as $permSlug) {
                $parts = explode('.', $permSlug);
                $module = $parts[0] ?? 'core';
                $permission = Permission::firstOrCreate(
                    ['slug' => $permSlug],
                    [
                        'name' => ucwords(str_replace('.', ' ', $permSlug)),
                        'module' => $module,
                    ]
                );
                $this->role->permissions()->attach($permission->id);
            }
        }

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($this->role->id);
        $this->user->branches()->attach($this->branch->id, ['is_default' => true]);

        $this->token = JWTAuth::fromUser($this->user);
    }

    protected function setUpSuperAdmin(): void
    {
        $this->user = User::factory()->superAdmin()->create();
        $this->token = JWTAuth::fromUser($this->user);
    }

    protected function authHeaders(array $extra = []): array
    {
        return array_merge([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'X-Branch-Id' => (string) ($this->branch->id ?? ''),
        ], $extra);
    }

    protected function adminHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    protected function apiGet(string $uri, array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->getJson("/api/v1{$uri}", $headers ?: $this->authHeaders());
    }

    protected function apiPost(string $uri, array $data = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/v1{$uri}", $data, $headers ?: $this->authHeaders());
    }

    protected function apiPut(string $uri, array $data = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->putJson("/api/v1{$uri}", $data, $headers ?: $this->authHeaders());
    }

    protected function apiPatch(string $uri, array $data = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->patchJson("/api/v1{$uri}", $data, $headers ?: $this->authHeaders());
    }

    protected function apiDelete(string $uri, array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->deleteJson("/api/v1{$uri}", [], $headers ?: $this->authHeaders());
    }

    protected function assertSuccessResponse(\Illuminate\Testing\TestResponse $response, int $status = 200): void
    {
        $response->assertStatus($status)->assertJsonStructure([
            'success',
            'message',
            'data',
        ])->assertJson(['success' => true]);
    }

    protected function assertCreatedResponse(\Illuminate\Testing\TestResponse $response): void
    {
        $this->assertSuccessResponse($response, 201);
    }

    protected function assertErrorResponse(\Illuminate\Testing\TestResponse $response, int $status = 400): void
    {
        $response->assertStatus($status)->assertJson(['success' => false]);
    }

    protected function assertUnauthorized(\Illuminate\Testing\TestResponse $response): void
    {
        $response->assertStatus(401);
    }

    protected function assertForbidden(\Illuminate\Testing\TestResponse $response): void
    {
        $response->assertStatus(403);
    }

    protected function assertPaginatedResponse(\Illuminate\Testing\TestResponse $response): void
    {
        $response->assertStatus(200)->assertJsonStructure([
            'success',
            'data',
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
    }

    /**
     * Ensure an open fiscal year and accounting period exist for the given date (defaults to today).
     * This prevents PeriodLockService from rejecting financial transactions in tests.
     */
    protected function setUpOpenFiscalPeriod(string $date = null, string $orgId = null): void
    {
        $date = $date ?? now()->format('Y-m-d');
        $year = (int) substr($date, 0, 4);
        $month = (int) substr($date, 5, 2);
        $organizationId = $orgId ?? ($this->organization->id ?? null);

        if (!$organizationId) {
            return;
        }

        $fy = FiscalYear::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        if (!$fy) {
            $fy = FiscalYear::withoutGlobalScopes()->forceCreate([
                'organization_id' => $organizationId,
                'name' => "FY{$year}",
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'is_closed' => false,
                'is_current' => true,
            ]);
        }

        $periodExists = AccountingPeriod::withoutGlobalScopes()
            ->where('fiscal_year_id', $fy->id)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();

        if (!$periodExists) {
            // Create a single period covering the entire fiscal year
            // to handle any date within the year (factories may use past dates)
            AccountingPeriod::withoutGlobalScopes()->forceCreate([
                'fiscal_year_id' => $fy->id,
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'is_closed' => false,
                'period_number' => 1,
                'period_type' => 'month',
            ]);
        }
    }

    /**
     * Seed the core currencies that are required by FK constraints (e.g. journal_entries.currency_code).
     * Uses firstOrCreate so tests can call setUpOrganization() multiple times safely.
     */
    private function seedBaseCurrencies(): void
    {
        $currencies = [
            ['code' => 'SAR', 'name' => 'Saudi Riyal',         'symbol' => 'ر.س', 'decimal_places' => 2],
            ['code' => 'AED', 'name' => 'UAE Dirham',           'symbol' => 'د.إ', 'decimal_places' => 2],
            ['code' => 'USD', 'name' => 'US Dollar',            'symbol' => '$',   'decimal_places' => 2],
            ['code' => 'INR', 'name' => 'Indian Rupee',         'symbol' => '₹',   'decimal_places' => 2],
        ];

        foreach ($currencies as $currency) {
            Currency::firstOrCreate(['code' => $currency['code']], $currency);
        }
    }
}
