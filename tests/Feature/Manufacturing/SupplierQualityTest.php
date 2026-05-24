<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class SupplierQualityTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();

        $this->supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    // ─── ratings ──────────────────────────────────────────────────────────────

    public function test_ratings_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/supplier-quality/ratings', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_store_rating_creates_it(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/supplier-quality/ratings',
            [
                'supplier_id'         => $this->supplier->id,
                'rating_period_start' => now()->subMonth()->format('Y-m-d'),
                'rating_period_end'   => now()->format('Y-m-d'),
                'classification'      => 'approved',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
    }

    // ─── avl ──────────────────────────────────────────────────────────────────

    public function test_avl_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/supplier-quality/avl', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── ncrs ─────────────────────────────────────────────────────────────────

    public function test_ncrs_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/supplier-quality/ncrs', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/supplier-quality/ratings')->assertUnauthorized();
    }
}
