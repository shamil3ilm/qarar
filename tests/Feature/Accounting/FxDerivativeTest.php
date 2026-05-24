<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\FxForward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class FxDerivativeTest extends TestCase
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

    private function makeForward(array $overrides = []): FxForward
    {
        return FxForward::create(array_merge([
            'organization_id' => $this->organization->id,
            'contract_number' => 'FWD-' . fake()->unique()->numerify('######'),
            'buy_currency'    => 'USD',
            'sell_currency'   => 'SAR',
            'notional_amount' => 100000.00,
            'forward_rate'    => 3.75,
            'trade_date'      => '2025-01-01',
            'maturity_date'   => '2025-06-30',
            'purpose'         => 'hedge',
            'status'          => 'active',
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeForward();
        $this->makeForward();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/fx-forwards');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/fx-forwards');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_books_fx_forward(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/fx-forwards', [
                'buy_currency'    => 'USD',
                'sell_currency'   => 'SAR',
                'notional_amount' => 50000,
                'forward_rate'    => 3.75,
                'trade_date'      => '2025-01-01',
                'maturity_date'   => '2025-06-30',
                'purpose'         => 'hedge',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/fx-forwards', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_forward_details(): void
    {
        $forward = $this->makeForward();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/fx-forwards/' . $forward->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $forward->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/fx-forwards/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Designate Hedge
    // -------------------------------------------------------------------------

    public function test_designate_hedge_creates_hedge_relation(): void
    {
        $forward = $this->makeForward();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/fx-forwards/' . $forward->uuid . '/designate-hedge', [
                'hedge_type'              => 'cash_flow',
                'hedged_item_type'        => 'forecast_sale',
                'hedged_item_description' => 'Q2 2025 expected sales',
                'designation_date'        => '2025-01-01',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Valuate
    // -------------------------------------------------------------------------

    public function test_valuate_records_mtm_valuation(): void
    {
        $forward = $this->makeForward();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/fx-forwards/' . $forward->uuid . '/valuate', [
                'valuation_date' => '2025-03-31',
                'spot_rate'      => 3.76,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Settle
    // -------------------------------------------------------------------------

    public function test_settle_marks_forward_as_exercised(): void
    {
        $forward = $this->makeForward(['status' => 'active']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/fx-forwards/' . $forward->uuid . '/settle', [
                'settlement_rate' => 3.78,
                'settlement_date' => '2025-06-30',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertEquals('exercised', $forward->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/fx-forwards')->assertStatus(401);
    }
}
