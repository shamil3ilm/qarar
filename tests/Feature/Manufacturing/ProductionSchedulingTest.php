<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ProductionSchedulingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    // ─── gantt ────────────────────────────────────────────────────────────────

    public function test_gantt_returns_data(): void
    {
        $from = now()->format('Y-m-d');
        $to   = now()->addDays(30)->format('Y-m-d');

        $response = $this->getJson(
            "/api/v1/manufacturing/schedule/gantt?from={$from}&to={$to}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/schedule/gantt')->assertUnauthorized();
    }
}
