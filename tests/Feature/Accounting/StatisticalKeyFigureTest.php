<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\StatisticalKeyFigure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class StatisticalKeyFigureTest extends TestCase
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

    private function makeSkf(array $overrides = []): StatisticalKeyFigure
    {
        return StatisticalKeyFigure::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'SKF-' . fake()->unique()->numerify('###'),
            'name'            => 'Headcount',
            'unit_of_measure' => 'employees',
            'skf_type'        => StatisticalKeyFigure::TYPE_FIXED,
            'is_active'       => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeSkf();
        $this->makeSkf();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/statistical-key-figures');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_statistical_key_figure(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/statistical-key-figures', [
                'code'            => 'HEAD',
                'name'            => 'Headcount',
                'unit_of_measure' => 'employees',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/statistical-key-figures', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_skf_type_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/statistical-key-figures', [
                'code'            => 'XX',
                'name'            => 'Test',
                'unit_of_measure' => 'units',
                'skf_type'        => 'invalid_type',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_details(): void
    {
        $skf = $this->makeSkf();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/statistical-key-figures/' . $skf->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $skf->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/statistical-key-figures/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_skf(): void
    {
        $skf = $this->makeSkf(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/statistical-key-figures/' . $skf->id, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $skf->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_skf(): void
    {
        $skf = $this->makeSkf();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/statistical-key-figures/' . $skf->id);

        $response->assertStatus(204);
        $this->assertNull(StatisticalKeyFigure::find($skf->id));
    }

    // -------------------------------------------------------------------------
    // Post Value
    // -------------------------------------------------------------------------

    public function test_post_value_validates_required_fields(): void
    {
        $skf = $this->makeSkf();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/statistical-key-figures/' . $skf->id . '/post-value', []);

        $response->assertStatus(422);
    }

    public function test_post_value_creates_value(): void
    {
        $skf = $this->makeSkf();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/statistical-key-figures/' . $skf->id . '/post-value', [
                'period'      => 3,
                'fiscal_year' => 2025,
                'value'       => 150,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Period Values
    // -------------------------------------------------------------------------

    public function test_period_values_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/statistical-key-figures/period-values');

        $response->assertStatus(422);
    }

    public function test_period_values_returns_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/statistical-key-figures/period-values?period=3&fiscal_year=2025');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/statistical-key-figures')->assertStatus(401);
    }
}
