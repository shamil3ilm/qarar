<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\LiquidityPlan;
use App\Models\Accounting\TreasuryInvestment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TreasuryTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeGlAccounts(): void
    {
        $accounts = [
            '1020' => ['type' => 'asset',  'sub_type' => 'bank',          'name' => 'Bank'],
            '1300' => ['type' => 'asset',  'sub_type' => 'other_asset',   'name' => 'Investments'],
            '1310' => ['type' => 'asset',  'sub_type' => 'receivable',    'name' => 'Accrued Interest Receivable'],
            '4100' => ['type' => 'income', 'sub_type' => 'other_income',  'name' => 'Interest Income'],
        ];

        foreach ($accounts as $code => $attrs) {
            Account::create([
                'organization_id' => $this->organization->id,
                'code'            => $code,
                'name'            => $attrs['name'],
                'account_type'    => $attrs['type'],
                'sub_type'        => $attrs['sub_type'],
                'is_active'       => true,
                'is_header'       => false,
                'level'           => 1,
                'path'            => $code,
            ]);
        }
    }

    private function makeInvestment(array $overrides = []): TreasuryInvestment
    {
        return TreasuryInvestment::create(array_merge([
            'organization_id'  => $this->organization->id,
            'instrument_number' => 'INV-' . fake()->unique()->numerify('######'),
            'instrument_type'  => 'fixed_deposit',
            'counterparty'     => 'National Bank',
            'principal_amount' => 100000.00,
            'interest_rate'    => 3.5,
            'investment_date'  => '2025-01-01',
            'maturity_date'    => '2025-12-31',
            'currency_code'    => 'SAR',
            'maturity_value'   => 103500.00,
            'accrued_interest' => 0,
            'status'           => TreasuryInvestment::STATUS_ACTIVE,
            'created_by'       => $this->user->id,
        ], $overrides));
    }

    private function makeLiquidityPlan(array $overrides = []): LiquidityPlan
    {
        return LiquidityPlan::create(array_merge([
            'organization_id' => $this->organization->id,
            'plan_name'       => 'Q1 Liquidity Plan',
            'plan_from'       => '2025-01-01',
            'plan_to'         => '2025-03-31',
            'granularity'     => 'monthly',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Investments Index
    // -------------------------------------------------------------------------

    public function test_investments_index_returns_list(): void
    {
        $this->makeInvestment();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/treasury/investments');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Investments Store
    // -------------------------------------------------------------------------

    public function test_investments_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/treasury/investments', []);

        $response->assertStatus(422);
    }

    public function test_investments_store_validates_instrument_type_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/treasury/investments', [
                'instrument_type'  => 'invalid',
                'counterparty'     => 'Bank',
                'principal_amount' => 100000.00,
                'interest_rate'    => 3.5,
                'investment_date'  => '2025-01-01',
                'maturity_date'    => '2025-12-31',
                'currency_code'    => 'SAR',
            ]);

        $response->assertStatus(422);
    }

    public function test_investments_store_creates_investment(): void
    {
        $this->makeGlAccounts();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/treasury/investments', [
                'instrument_type'  => 'fixed_deposit',
                'counterparty'     => 'National Bank',
                'principal_amount' => 50000.00,
                'interest_rate'    => 4.0,
                'investment_date'  => '2025-01-01',
                'maturity_date'    => '2025-12-31',
                'currency_code'    => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Investments Show
    // -------------------------------------------------------------------------

    public function test_investments_show_returns_details(): void
    {
        $investment = $this->makeInvestment();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/treasury/investments/' . $investment->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_investments_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/treasury/investments/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Accrue Interest
    // -------------------------------------------------------------------------

    public function test_accrue_validates_as_of_date_required(): void
    {
        $investment = $this->makeInvestment();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/treasury/investments/' . $investment->uuid . '/accrue', []);

        $response->assertStatus(422);
    }

    public function test_accrue_returns_success(): void
    {
        $this->makeGlAccounts();
        $investment = $this->makeInvestment();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/treasury/investments/' . $investment->uuid . '/accrue', [
                'as_of_date' => '2025-06-30',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Bank Positions
    // -------------------------------------------------------------------------

    public function test_bank_positions_returns_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/treasury/bank-positions');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Position Summary
    // -------------------------------------------------------------------------

    public function test_position_summary_returns_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/treasury/position-summary');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Maturing Investments
    // -------------------------------------------------------------------------

    public function test_maturing_investments_returns_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/treasury/maturing-investments');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Liquidity Plans
    // -------------------------------------------------------------------------

    public function test_liquidity_plans_returns_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/treasury/liquidity-plans');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_create_liquidity_plan_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/treasury/liquidity-plans', []);

        $response->assertStatus(422);
    }

    public function test_create_liquidity_plan_creates_plan(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/treasury/liquidity-plans', [
                'plan_name'   => 'Q1 Plan',
                'plan_from'   => '2025-01-01',
                'plan_to'     => '2025-03-31',
                'granularity' => 'monthly',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_show_liquidity_plan_returns_details(): void
    {
        $plan = $this->makeLiquidityPlan();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/treasury/liquidity-plans/' . $plan->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/treasury/investments')->assertStatus(401);
    }
}
