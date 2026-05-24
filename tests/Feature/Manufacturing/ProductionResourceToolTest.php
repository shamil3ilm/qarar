<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\ProductionResourceTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ProductionResourceToolTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_tools(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/production-resources', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── available ────────────────────────────────────────────────────────────

    public function test_available_returns_tools(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/production-resources/available', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_tool(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/production-resources',
            [
                'prt_number' => 'PRT-001',
                'prt_name'   => 'Test Fixture',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('production_resource_tools', [
            'prt_number' => 'PRT-001',
            'prt_name'   => 'Test Fixture',
        ]);
    }

    public function test_store_requires_prt_number_and_name(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/production-resources',
            [],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_tool(): void
    {
        $tool = ProductionResourceTool::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/production-resources/{$tool->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_modifies_tool(): void
    {
        $tool = ProductionResourceTool::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->putJson(
            "/api/v1/manufacturing/production-resources/{$tool->id}",
            ['prt_name' => 'Updated Name'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_tool(): void
    {
        $tool = ProductionResourceTool::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/production-resources/{$tool->id}",
            [],
            $this->authHeaders()
        );

        $response->assertNoContent();
        $this->assertSoftDeleted('production_resource_tools', ['id' => $tool->id]);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/production-resources')->assertUnauthorized();
    }
}
