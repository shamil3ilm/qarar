<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DocumentTypeTest extends TestCase
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

    private function makeDocumentType(array $overrides = []): DocumentType
    {
        return DocumentType::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'DT' . fake()->unique()->numerify('##'),
            'name'            => 'Test Document Type',
            'is_active'       => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeDocumentType();
        $this->makeDocumentType();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/document-types');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/document-types');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_document_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/document-types', [
                'code' => 'SA',
                'name' => 'Sales Invoice',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'SA');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/document-types', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_unique_code_per_org(): void
    {
        $this->makeDocumentType(['code' => 'DUP']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/document-types', [
                'code' => 'DUP',
                'name' => 'Duplicate',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_document_type_details(): void
    {
        $dt = $this->makeDocumentType();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/document-types/' . $dt->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $dt->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/document-types/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_document_type(): void
    {
        $dt = $this->makeDocumentType(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/document-types/' . $dt->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_document_type(): void
    {
        $dt = $this->makeDocumentType();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/document-types/' . $dt->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('accounting_document_types', ['id' => $dt->id]);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/document-types')->assertStatus(401);
    }
}
