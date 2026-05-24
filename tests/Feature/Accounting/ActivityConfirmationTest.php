<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CoActivityConfirmation;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\ActivityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ActivityConfirmationTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.controlling.activity-confirmation.view',
            'accounting.controlling.activity-confirmation.create',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCostCenter(): CostCenter
    {
        return CostCenter::create([
            'organization_id' => $this->organization->id,
            'code'            => 'CC-' . fake()->unique()->numerify('###'),
            'name'            => 'Test Cost Center',
            'status'          => 'active',
        ]);
    }

    private function makeActivityType(): ActivityType
    {
        return ActivityType::create([
            'organization_id' => $this->organization->id,
            'code'            => 'AT-' . fake()->unique()->numerify('###'),
            'name'            => 'Test Activity',
            'unit_of_measure' => 'hours',
        ]);
    }

    private function makeConfirmation(CostCenter $cc, ActivityType $at, array $overrides = []): CoActivityConfirmation
    {
        return CoActivityConfirmation::create(array_merge([
            'organization_id'     => $this->organization->id,
            'confirmation_number' => 'CONF-' . fake()->unique()->numerify('########'),
            'cost_center_id'      => $cc->id,
            'activity_type_id'    => $at->id,
            'confirmed_quantity'  => 10.0,
            'fiscal_year'         => 2025,
            'period'              => 3,
            'confirmation_date'   => '2025-03-31',
            'confirmed_by'        => $this->user->id,
            'status'              => CoActivityConfirmation::STATUS_CONFIRMED,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $cc = $this->makeCostCenter();
        $at = $this->makeActivityType();
        $this->makeConfirmation($cc, $at);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/activity-confirmations');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/activity-confirmations', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_confirmation(): void
    {
        $cc = $this->makeCostCenter();
        $at = $this->makeActivityType();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/activity-confirmations', [
                'cost_center_id'     => $cc->id,
                'activity_type_id'   => $at->id,
                'confirmed_quantity' => 8.0,
                'fiscal_year'        => 2025,
                'period'             => 3,
                'confirmation_date'  => '2025-03-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_details(): void
    {
        $cc   = $this->makeCostCenter();
        $at   = $this->makeActivityType();
        $conf = $this->makeConfirmation($cc, $at);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/activity-confirmations/' . $conf->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/activity-confirmations/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Reverse
    // -------------------------------------------------------------------------

    public function test_reverse_creates_reversal(): void
    {
        $cc   = $this->makeCostCenter();
        $at   = $this->makeActivityType();
        $conf = $this->makeConfirmation($cc, $at);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/activity-confirmations/' . $conf->uuid . '/reverse');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_reverse_rejects_already_reversed(): void
    {
        $cc   = $this->makeCostCenter();
        $at   = $this->makeActivityType();
        $conf = $this->makeConfirmation($cc, $at, ['status' => CoActivityConfirmation::STATUS_REVERSED]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/activity-confirmations/' . $conf->uuid . '/reverse');

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/controlling/activity-confirmations')->assertStatus(401);
    }
}
