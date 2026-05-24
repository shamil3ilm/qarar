<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CapacityLevelingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['manufacturing.capacity.view']);
    }

    // ─── suggest ──────────────────────────────────────────────────────────────

    public function test_suggest_returns_suggestions(): void
    {
        $from = now()->format('Y-m-d');
        $to   = now()->addDays(14)->format('Y-m-d');

        $response = $this->getJson(
            "/api/v1/manufacturing/capacity-leveling/suggest?from={$from}&to={$to}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_suggest_requires_dates(): void
    {
        $response = $this->getJson(
            '/api/v1/manufacturing/capacity-leveling/suggest',
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/capacity-leveling/suggest')->assertUnauthorized();
    }
}
