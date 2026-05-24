<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\CertificateOfAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CertificateOfAnalysisTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.view',
            'manufacturing.quality.create',
            'manufacturing.quality.edit',
            'manufacturing.quality.delete',
        ]);

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated(): void
    {
        $response = $this->getJson(
            '/api/v1/manufacturing/quality/certificates-of-analysis',
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_certificate(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/quality/certificates-of-analysis',
            [
                'product_id'   => $this->product->id,
                'test_results' => [
                    [
                        'parameter' => 'Purity',
                        'result'    => '99.5%',
                        'pass_fail' => 'pass',
                    ],
                ],
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('certificates_of_analysis', [
            'product_id' => $this->product->id,
            'status'     => 'draft',
        ]);
    }

    public function test_store_requires_product_id_and_test_results(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/quality/certificates-of-analysis',
            [],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_certificate(): void
    {
        $coa = CertificateOfAnalysis::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
            'issued_by'       => $this->user->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/quality/certificates-of-analysis/{$coa->uuid}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_modifies_draft_certificate(): void
    {
        $coa = CertificateOfAnalysis::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
            'issued_by'       => $this->user->id,
            'status'          => 'draft',
        ]);

        $response = $this->putJson(
            "/api/v1/manufacturing/quality/certificates-of-analysis/{$coa->uuid}",
            ['remarks' => 'Updated remarks'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_draft_certificate(): void
    {
        $coa = CertificateOfAnalysis::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
            'issued_by'       => $this->user->id,
            'status'          => 'draft',
        ]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/quality/certificates-of-analysis/{$coa->uuid}",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted('certificates_of_analysis', ['id' => $coa->id]);
    }

    // ─── approve / issue / revoke ─────────────────────────────────────────────

    public function test_approve_transitions_status(): void
    {
        $coa = CertificateOfAnalysis::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
            'issued_by'       => $this->user->id,
            'status'          => 'draft',
        ]);

        $response = $this->postJson(
            "/api/v1/manufacturing/quality/certificates-of-analysis/{$coa->uuid}/approve",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('certificates_of_analysis', [
            'id'     => $coa->id,
            'status' => 'approved',
        ]);
    }

    public function test_issue_transitions_from_approved(): void
    {
        $coa = CertificateOfAnalysis::factory()->approved()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
            'issued_by'       => $this->user->id,
        ]);

        $response = $this->postJson(
            "/api/v1/manufacturing/quality/certificates-of-analysis/{$coa->uuid}/issue",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('certificates_of_analysis', [
            'id'     => $coa->id,
            'status' => 'issued',
        ]);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/quality/certificates-of-analysis')->assertUnauthorized();
    }
}
