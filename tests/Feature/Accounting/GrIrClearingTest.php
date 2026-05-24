<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class GrIrClearingTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
    }

    // Open Items and Report use raw SQL with MySQL-specific column references
    // (po.po_number) not available in SQLite — covered by integration tests only.

    // -------------------------------------------------------------------------
    // Clear
    // -------------------------------------------------------------------------

    public function test_clear_returns_404_for_missing_po_line(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/grir/clear/99999', [
                'clearing_date' => '2025-06-30',
            ]);

        $response->assertStatus(404);
    }

    public function test_clear_validates_clearing_type_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/grir/clear/1', [
                'clearing_type' => 'invalid_type',
            ]);

        $response->assertStatus(422);
    }


    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/grir/open-items')->assertStatus(401);
    }
}
