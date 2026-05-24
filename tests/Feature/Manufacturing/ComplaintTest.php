<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\Complaint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ComplaintTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_complaints(): void
    {
        Complaint::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/complaints', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_complaint(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/complaints',
            [
                'complaint_number'  => 'CMP-2026-001',
                'complaint_source'  => 'customer',
                'subject'           => 'Product quality issue',
                'description'       => 'Customer reported defects in delivered batch',
                'priority'          => 'high',
                'received_date'     => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('complaints', [
            'complaint_number' => 'CMP-2026-001',
            'organization_id'  => $this->organization->id,
        ]);
    }

    public function test_store_requires_complaint_number(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/complaints',
            [
                'complaint_source' => 'customer',
                'subject'          => 'Issue',
                'description'      => 'Detail',
                'priority'         => 'high',
                'received_date'    => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_description(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/complaints',
            [
                'complaint_number' => 'CMP-2026-002',
                'complaint_source' => 'customer',
                'subject'          => 'Issue',
                'priority'         => 'high',
                'received_date'    => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_complaint(): void
    {
        $complaint = Complaint::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/complaints/{$complaint->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── addCommunication ─────────────────────────────────────────────────────

    public function test_add_communication_creates_log(): void
    {
        $complaint = Complaint::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->postJson(
            "/api/v1/manufacturing/complaints/{$complaint->id}/communications",
            [
                'direction' => 'inbound',
                'channel'   => 'email',
                'content'   => 'Customer replied with additional details',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('complaint_communications', [
            'complaint_id' => $complaint->id,
        ]);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/complaints')->assertUnauthorized();
    }
}
