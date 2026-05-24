<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class SpcTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['manufacturing.quality.view', 'manufacturing.quality.create']);
    }

    // ─── calculateXbarR ───────────────────────────────────────────────────────

    public function test_xbar_r_returns_statistics(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/spc/xbar-r',
            [
                'samples' => [
                    [10.1, 10.2, 9.9],
                    [10.0, 10.3, 10.1],
                    [9.8, 10.0, 10.2],
                ],
            ],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_xbar_r_requires_samples(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/spc/xbar-r',
            [],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── calculateCpk ────────────────────────────────────────────────────────

    public function test_cpk_returns_capability_indices(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/spc/cpk',
            [
                'measurements' => [10.1, 9.9, 10.0, 10.2, 9.8, 10.1],
                'usl'          => 10.5,
                'lsl'          => 9.5,
            ],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_cpk_requires_measurements(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/spc/cpk',
            ['usl' => 10.5, 'lsl' => 9.5],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->postJson('/api/v1/manufacturing/spc/xbar-r')->assertUnauthorized();
    }
}
