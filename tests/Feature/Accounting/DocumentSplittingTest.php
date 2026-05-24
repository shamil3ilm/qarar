<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\DocumentSplittingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DocumentSplittingTest extends TestCase
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

    private function makeRule(array $overrides = []): DocumentSplittingRule
    {
        return DocumentSplittingRule::create(array_merge([
            'organization_id' => $this->organization->id,
            'name'            => 'Test Rule ' . fake()->unique()->numerify('####'),
            'split_method'    => 'profit_center',
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
            ->getJson('/api/v1/document-splitting-rules');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/document-splitting-rules');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_splitting_rule(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/document-splitting-rules', [
                'name'         => 'Profit Center Split',
                'split_method' => 'profit_center',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/document-splitting-rules', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_split_method_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/document-splitting-rules', [
                'name'         => 'Test',
                'split_method' => 'invalid_method',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_rule(): void
    {
        $rule = $this->makeRule(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/document-splitting-rules/' . $rule->id, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $rule->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_rule(): void
    {
        $rule = $this->makeRule();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/document-splitting-rules/' . $rule->id);

        $response->assertStatus(200);
        $this->assertNull(DocumentSplittingRule::find($rule->id));
    }

    // -------------------------------------------------------------------------
    // Preview
    // -------------------------------------------------------------------------

    public function test_split_preview_returns_simulation(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/document-splitting-rules/preview', [
                'lines' => [
                    ['debit' => 1000, 'credit' => 0],
                    ['debit' => 0, 'credit' => 1000],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_split_preview_validates_lines_required(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/document-splitting-rules/preview', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/document-splitting-rules')->assertStatus(401);
    }
}
