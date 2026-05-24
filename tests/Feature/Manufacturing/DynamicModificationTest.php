<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\QmDynamicModificationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DynamicModificationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_rules(): void
    {
        QmDynamicModificationRule::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/dynamic-modification-rules', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_rule(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/dynamic-modification-rules',
            [
                'rule_code'                     => 'DMR-001',
                'name'                          => 'Standard DMR',
                'tighten_consecutive_fails'     => 2,
                'reduce_after_consecutive_pass' => 5,
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('qm_dynamic_modification_rules', [
            'rule_code'       => 'DMR-001',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_requires_rule_code(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/dynamic-modification-rules',
            ['name' => 'Test Rule'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_name(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/dynamic-modification-rules',
            ['rule_code' => 'DMR-002'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_rule(): void
    {
        $rule = QmDynamicModificationRule::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/dynamic-modification-rules/{$rule->uuid}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── currentStage ─────────────────────────────────────────────────────────

    public function test_current_stage_returns_data(): void
    {
        $product = \App\Models\Inventory\Product::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $rule = QmDynamicModificationRule::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/dynamic-modification-rules/{$rule->uuid}/stage?product_id={$product->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/dynamic-modification-rules')->assertUnauthorized();
    }
}
