<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\ProductionLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class RepetitiveManufacturingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    // ─── lines ────────────────────────────────────────────────────────────────

    public function test_index_lines_returns_paginated(): void
    {
        ProductionLine::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/repetitive-manufacturing/lines', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_store_line_creates_it(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/repetitive-manufacturing/lines',
            ['code' => 'PL-001', 'name' => 'Assembly Line 1'],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('production_lines', [
            'code'            => 'PL-001',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_line_requires_code(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/repetitive-manufacturing/lines',
            ['name' => 'Line Without Code'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── schedules ────────────────────────────────────────────────────────────

    public function test_index_schedules_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/repetitive-manufacturing/schedules', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/repetitive-manufacturing/lines')->assertUnauthorized();
    }
}
