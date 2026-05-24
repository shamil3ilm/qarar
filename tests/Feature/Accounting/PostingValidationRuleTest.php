<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\PostingValidationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PostingValidationRuleTest extends TestCase
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

    private function makeRule(array $overrides = []): PostingValidationRule
    {
        return PostingValidationRule::create(array_merge([
            'organization_id' => $this->organization->id,
            'rule_name'       => 'Test Rule ' . fake()->unique()->numerify('###'),
            'rule_type'       => 'validation',
            'trigger_event'   => 'on_save',
            'conditions'      => [['field' => 'amount', 'operator' => 'gt', 'value' => 0]],
            'actions'         => [['field' => 'status', 'action_type' => 'set', 'value' => 'valid']],
            'is_active'       => true,
            'priority'        => 10,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeRule();
        $this->makeRule();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/posting-validation-rules');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/posting-validation-rules');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_validation_rule(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/posting-validation-rules', [
                'rule_name'     => 'Amount Check',
                'rule_type'     => 'validation',
                'trigger_event' => 'on_post',
                'conditions'    => [['field' => 'amount', 'operator' => 'gt']],
                'actions'       => [['field' => 'status', 'action_type' => 'set']],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/posting-validation-rules', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_rule_type_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/posting-validation-rules', [
                'rule_name'     => 'Test',
                'rule_type'     => 'invalid_type',
                'trigger_event' => 'on_save',
                'conditions'    => [['field' => 'x', 'operator' => 'eq']],
                'actions'       => [['field' => 'y', 'action_type' => 'set']],
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_rule_details(): void
    {
        $rule = $this->makeRule();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/posting-validation-rules/' . $rule->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $rule->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/posting-validation-rules/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_rule(): void
    {
        $rule = $this->makeRule(['rule_name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/posting-validation-rules/' . $rule->id, [
                'rule_name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $rule->fresh()->rule_name);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_rule(): void
    {
        $rule = $this->makeRule();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/posting-validation-rules/' . $rule->id);

        $response->assertStatus(200);
        $this->assertNull(PostingValidationRule::find($rule->id));
    }

    // -------------------------------------------------------------------------
    // Evaluate
    // -------------------------------------------------------------------------

    public function test_evaluate_validates_document_data_required(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/posting-validation-rules/evaluate', []);

        $response->assertStatus(422);
    }

    public function test_evaluate_returns_evaluated_document(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/posting-validation-rules/evaluate', [
                'document_data' => ['amount' => 500, 'currency' => 'SAR'],
                'trigger_event' => 'on_save',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['original', 'evaluated']]);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/posting-validation-rules')->assertStatus(401);
    }
}
