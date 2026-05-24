<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CostElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CostElementTest extends TestCase
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

    private function makeCostElement(array $overrides = []): CostElement
    {
        return CostElement::create(array_merge([
            'organization_id'      => $this->organization->id,
            'code'                 => 'CE-' . fake()->unique()->numerify('####'),
            'name'                 => 'Test Cost Element',
            'element_type'         => CostElement::TYPE_SECONDARY,
            'cost_element_category' => CostElement::CATEGORY_GENERAL,
            'is_active'            => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeCostElement();
        $this->makeCostElement();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cost-elements');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cost-elements');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_cost_element(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cost-elements', [
                'code'         => 'CE-SALARY',
                'name'         => 'Salaries',
                'element_type' => CostElement::TYPE_SECONDARY,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'CE-SALARY');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cost-elements', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_element_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cost-elements', [
                'code'         => 'CE-X',
                'name'         => 'Test',
                'element_type' => 'invalid_type',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_cost_element(): void
    {
        $ce = $this->makeCostElement();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cost-elements/' . $ce->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $ce->id);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_cost_element(): void
    {
        $ce = $this->makeCostElement(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/cost-elements/' . $ce->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_cost_element(): void
    {
        $ce = $this->makeCostElement();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/cost-elements/' . $ce->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('cost_elements', ['id' => $ce->id]);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/cost-elements')->assertStatus(401);
    }
}
