<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\ParkedDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ParkedDocumentTest extends TestCase
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

    private function makeDocument(array $overrides = []): ParkedDocument
    {
        return ParkedDocument::create(array_merge([
            'organization_id' => $this->organization->id,
            'document_type'   => 'vendor_invoice',
            'document_date'   => '2025-01-15',
            'posting_date'    => '2025-01-15',
            'document_data'   => ['lines' => []],
            'total_debit'     => 1000.00,
            'total_credit'    => 1000.00,
            'currency_code'   => 'SAR',
            'status'          => ParkedDocument::STATUS_PARKED,
            'parked_by'       => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeDocument();
        $this->makeDocument();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/parked-documents');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/parked-documents');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_parks_a_document(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parked-documents', [
                'document_type' => 'vendor_invoice',
                'document_date' => '2025-01-15',
                'posting_date'  => '2025-01-15',
                'document_data' => ['description' => 'Test'],
                'total_debit'   => 500.00,
                'total_credit'  => 500.00,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parked-documents', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_document_details(): void
    {
        $doc = $this->makeDocument();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/parked-documents/' . $doc->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $doc->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/parked-documents/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_parked_document(): void
    {
        $doc = $this->makeDocument(['status' => ParkedDocument::STATUS_PARKED]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/parked-documents/' . $doc->uuid, [
                'reference' => 'REF-001',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('REF-001', $doc->fresh()->reference);
    }

    public function test_update_rejects_posted_document(): void
    {
        $doc = $this->makeDocument(['status' => ParkedDocument::STATUS_POSTED]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/parked-documents/' . $doc->uuid, [
                'reference' => 'REF-001',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Approve
    // -------------------------------------------------------------------------

    public function test_approve_marks_parked_document(): void
    {
        $doc = $this->makeDocument(['status' => ParkedDocument::STATUS_PARKED]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parked-documents/' . $doc->uuid . '/approve');

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_rejects_posted_document(): void
    {
        $doc = $this->makeDocument(['status' => ParkedDocument::STATUS_POSTED]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/parked-documents/' . $doc->uuid);

        $response->assertStatus(422);
    }

    public function test_destroy_soft_deletes_parked_document(): void
    {
        $doc = $this->makeDocument(['status' => ParkedDocument::STATUS_PARKED]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/parked-documents/' . $doc->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('parked_documents', ['id' => $doc->id]);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/parked-documents')->assertStatus(401);
    }
}
