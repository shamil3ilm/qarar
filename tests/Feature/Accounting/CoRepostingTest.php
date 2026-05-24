<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CoReposting;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\CostElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CoRepostingTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.controlling.reposting.view',
            'accounting.controlling.reposting.create',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCostElement(): CostElement
    {
        return CostElement::create([
            'organization_id'      => $this->organization->id,
            'code'                 => 'CE-' . fake()->unique()->numerify('####'),
            'name'                 => 'Test Cost Element',
            'element_type'         => 'secondary',
            'cost_element_category' => 'general',
            'is_active'            => true,
        ]);
    }

    private function makeCostCenter(): CostCenter
    {
        return CostCenter::create([
            'organization_id' => $this->organization->id,
            'code'            => 'CC-' . fake()->unique()->numerify('####'),
            'name'            => 'Test Cost Center',
            'status'          => 'active',
        ]);
    }

    private function makeReposting(array $overrides = []): CoReposting
    {
        $ce  = $this->makeCostElement();
        $cc1 = $this->makeCostCenter();
        $cc2 = $this->makeCostCenter();

        return CoReposting::create(array_merge([
            'organization_id'  => $this->organization->id,
            'reposting_number' => 'KR-' . fake()->unique()->numerify('######'),
            'posting_date'     => now()->toDateString(),
            'document_date'    => now()->toDateString(),
            'period'           => 1,
            'fiscal_year'      => 2025,
            'from_type'        => CoReposting::FROM_COST_CENTER,
            'from_id'          => $cc1->id,
            'to_type'          => CoReposting::FROM_COST_CENTER,
            'to_id'            => $cc2->id,
            'cost_element_id'  => $ce->id,
            'amount'           => 10000.00,
            'currency_code'    => 'SAR',
            'status'           => CoReposting::STATUS_POSTED,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeReposting();
        $this->makeReposting();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/co-repostings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/co-repostings');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_reposting(): void
    {
        $ce  = $this->makeCostElement();
        $cc1 = $this->makeCostCenter();
        $cc2 = $this->makeCostCenter();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/co-repostings', [
                'posting_date'    => now()->toDateString(),
                'period'          => 3,
                'fiscal_year'     => 2025,
                'from_type'       => CoReposting::FROM_COST_CENTER,
                'from_id'         => $cc1->id,
                'to_type'         => CoReposting::FROM_COST_CENTER,
                'to_id'           => $cc2->id,
                'cost_element_id' => $ce->id,
                'amount'          => 5000.00,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/co-repostings', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_period_range(): void
    {
        $ce = $this->makeCostElement();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/co-repostings', [
                'posting_date'    => now()->toDateString(),
                'period'          => 13, // > 12
                'fiscal_year'     => 2025,
                'from_type'       => CoReposting::FROM_COST_CENTER,
                'from_id'         => 1,
                'to_type'         => CoReposting::FROM_COST_CENTER,
                'to_id'           => 2,
                'cost_element_id' => $ce->id,
                'amount'          => 5000.00,
            ]);

        $response->assertStatus(422);
    }

    public function test_store_validates_invalid_from_type(): void
    {
        $ce = $this->makeCostElement();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/co-repostings', [
                'posting_date'    => now()->toDateString(),
                'period'          => 1,
                'fiscal_year'     => 2025,
                'from_type'       => 'invalid_type',
                'from_id'         => 1,
                'to_type'         => CoReposting::FROM_COST_CENTER,
                'to_id'           => 2,
                'cost_element_id' => $ce->id,
                'amount'          => 5000.00,
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_reposting_details(): void
    {
        $reposting = $this->makeReposting();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/co-repostings/' . $reposting->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $reposting->id);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_posted_reposting(): void
    {
        $reposting = $this->makeReposting();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/controlling/co-repostings/' . $reposting->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('co_repostings', ['id' => $reposting->id]);
    }

    public function test_destroy_blocks_reversed_reposting(): void
    {
        $reposting = $this->makeReposting(['status' => CoReposting::STATUS_REVERSED]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/controlling/co-repostings/' . $reposting->uuid);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/controlling/co-repostings')->assertStatus(401);
    }
}
