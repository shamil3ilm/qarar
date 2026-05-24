<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\ConsolidationGroup;
use App\Models\Accounting\ConsolidationPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ConsolidationTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.consolidation.view',
            'accounting.consolidation.create',
            'accounting.consolidation.edit',
            'accounting.consolidation.delete',
            'accounting.consolidation.complete',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeGroup(array $overrides = []): ConsolidationGroup
    {
        return ConsolidationGroup::create(array_merge([
            'organization_id' => $this->organization->id,
            'name'            => 'Test Group',
            'currency_code'   => 'SAR',
            'is_active'       => true,
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    private function makePeriod(ConsolidationGroup $group, array $overrides = []): ConsolidationPeriod
    {
        return ConsolidationPeriod::create(array_merge([
            'organization_id'         => $this->organization->id,
            'consolidation_group_id'  => $group->id,
            'period_start'            => '2025-01-01',
            'period_end'              => '2025-12-31',
            'status'                  => ConsolidationPeriod::STATUS_OPEN,
            'created_by'              => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Groups — index
    // -------------------------------------------------------------------------

    public function test_index_groups_returns_list(): void
    {
        $this->makeGroup(['name' => 'Group A']);
        $this->makeGroup(['name' => 'Group B']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/groups');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Groups — store
    // -------------------------------------------------------------------------

    public function test_store_group_creates_group(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/groups', [
                'name'          => 'Gulf Entities',
                'currency_code' => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Gulf Entities');
    }

    public function test_store_group_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/groups', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Groups — show
    // -------------------------------------------------------------------------

    public function test_show_group_returns_details(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/groups/' . $group->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $group->id);
    }

    public function test_show_group_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/groups/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Groups — update
    // -------------------------------------------------------------------------

    public function test_update_group_modifies_group(): void
    {
        $group = $this->makeGroup(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/consolidation/groups/' . $group->id, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    // -------------------------------------------------------------------------
    // Groups — destroy
    // -------------------------------------------------------------------------

    public function test_destroy_group_deletes_group(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/consolidation/groups/' . $group->id);

        $response->assertStatus(200);
        $this->assertNull(ConsolidationGroup::find($group->id));
    }

    public function test_destroy_group_blocks_if_completed_periods_exist(): void
    {
        $group  = $this->makeGroup();
        $this->makePeriod($group, ['status' => ConsolidationPeriod::STATUS_COMPLETED]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/consolidation/groups/' . $group->id);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Groups — add entity
    // -------------------------------------------------------------------------

    public function test_add_entity_to_group(): void
    {
        $group     = $this->makeGroup();
        $otherOrg  = \App\Models\Core\Organization::factory()->create();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/groups/' . $group->id . '/entities', [
                'entity_organization_id' => $otherOrg->id,
                'name'                   => 'Subsidiary A',
                'ownership_percent'      => 100,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Subsidiary A');
    }

    // -------------------------------------------------------------------------
    // Periods — index
    // -------------------------------------------------------------------------

    public function test_index_periods_returns_list(): void
    {
        $group = $this->makeGroup();
        $this->makePeriod($group);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/periods');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Periods — store
    // -------------------------------------------------------------------------

    public function test_store_period_creates_period(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/periods', [
                'consolidation_group_id' => $group->id,
                'period_start'           => '2025-01-01',
                'period_end'             => '2025-12-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.consolidation_group_id', $group->id);
    }

    public function test_store_period_validates_dates(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/periods', [
                'consolidation_group_id' => $group->id,
                'period_start'           => '2025-12-31',
                'period_end'             => '2025-01-01', // before start
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Periods — show
    // -------------------------------------------------------------------------

    public function test_show_period_returns_details(): void
    {
        $group  = $this->makeGroup();
        $period = $this->makePeriod($group);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/periods/' . $period->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $period->id);
    }

    // -------------------------------------------------------------------------
    // Periods — collect balances
    // -------------------------------------------------------------------------

    public function test_collect_balances_succeeds_for_open_period(): void
    {
        $group  = $this->makeGroup();
        $period = $this->makePeriod($group);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/periods/' . $period->id . '/collect-balances');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_collect_balances_blocked_for_completed_period(): void
    {
        $group  = $this->makeGroup();
        $period = $this->makePeriod($group, ['status' => ConsolidationPeriod::STATUS_COMPLETED]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/periods/' . $period->id . '/collect-balances');

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/consolidation/groups')->assertStatus(401);
        $this->getJson('/api/v1/consolidation/periods')->assertStatus(401);
    }
}
