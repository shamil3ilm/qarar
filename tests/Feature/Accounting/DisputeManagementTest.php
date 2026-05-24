<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\DisputeCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DisputeManagementTest extends TestCase
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

    private function makeCase(array $overrides = []): DisputeCase
    {
        return DisputeCase::create(array_merge([
            'organization_id' => $this->organization->id,
            'case_number'     => 'DC-' . fake()->unique()->numerify('####'),
            'document_type'   => 'invoice',
            'document_id'     => 1,
            'contact_id'      => 1,
            'disputed_amount' => 1000.00,
            'dispute_reason'  => 'pricing',
            'status'          => 'open',
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeCase();
        $this->makeCase();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/disputes');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/disputes');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_dispute_case(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/disputes', [
                'document_type'   => 'invoice',
                'document_id'     => 1,
                'contact_id'      => 1,
                'disputed_amount' => 500.00,
                'dispute_reason'  => 'pricing',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/disputes', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_case_details(): void
    {
        $case = $this->makeCase();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/disputes/' . $case->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $case->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/disputes/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_case(): void
    {
        $case = $this->makeCase();

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/disputes/' . $case->uuid, [
                'status' => 'in_review',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('in_review', $case->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Resolve
    // -------------------------------------------------------------------------

    public function test_resolve_case_marks_resolved(): void
    {
        $case = $this->makeCase(['status' => 'in_review']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/disputes/' . $case->uuid . '/resolve', [
                'resolution_notes' => 'Agreed on revised pricing.',
                'resolved_amount'  => 900.00,
            ]);

        $response->assertStatus(200);
        $this->assertEquals('resolved', $case->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Close
    // -------------------------------------------------------------------------

    public function test_close_case_marks_closed(): void
    {
        $case = $this->makeCase(['status' => 'resolved']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/disputes/' . $case->uuid . '/close');

        $response->assertStatus(200);
        $this->assertEquals('closed', $case->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Collections Worklist
    // -------------------------------------------------------------------------

    public function test_collections_worklist_returns_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/disputes/collections-worklist');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/disputes')->assertStatus(401);
    }
}
