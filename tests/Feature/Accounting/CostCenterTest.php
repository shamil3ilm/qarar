<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CostCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CostCenterTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.controlling.cost-center.view',
            'accounting.controlling.cost-center.create',
            'accounting.controlling.cost-center.update',
            'accounting.controlling.cost-center.delete',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCostCenter(array $overrides = []): CostCenter
    {
        return CostCenter::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'CC-' . fake()->unique()->numerify('####'),
            'name'            => 'Test Cost Center',
            'status'          => CostCenter::STATUS_ACTIVE,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeCostCenter();
        $this->makeCostCenter();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_for_new_org(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_cost_center(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/cost-centers', [
                'code' => 'CC-SALES',
                'name' => 'Sales Department',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'CC-SALES');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/cost-centers', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_cost_center_details(): void
    {
        $cc = $this->makeCostCenter();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers/' . $cc->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $cc->id);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_cost_center(): void
    {
        $cc = $this->makeCostCenter(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/controlling/cost-centers/' . $cc->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_cost_center(): void
    {
        $cc = $this->makeCostCenter();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/controlling/cost-centers/' . $cc->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('cost_centers', ['id' => $cc->id]);
    }

    // -------------------------------------------------------------------------
    // Deactivate
    // -------------------------------------------------------------------------

    public function test_deactivate_marks_as_inactive(): void
    {
        $cc = $this->makeCostCenter();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/cost-centers/' . $cc->uuid . '/deactivate');

        $response->assertStatus(200);
        $this->assertEquals(CostCenter::STATUS_INACTIVE, $cc->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Hierarchy tree
    // -------------------------------------------------------------------------

    public function test_hierarchy_tree_returns_nested_structure(): void
    {
        $parent = $this->makeCostCenter(['name' => 'Parent CC']);
        $this->makeCostCenter(['name' => 'Child CC', 'parent_id' => $parent->id]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers/hierarchy-tree');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Report
    // -------------------------------------------------------------------------

    public function test_report_all_returns_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers/report?from=2025-01-01&to=2025-12-31');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_report_validates_required_dates(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers/report');

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/controlling/cost-centers')->assertStatus(401);
    }
}
