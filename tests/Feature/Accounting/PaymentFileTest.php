<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PaymentFileTest extends TestCase
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
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-files');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-files/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Generate
    // -------------------------------------------------------------------------

    public function test_generate_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-files/generate', []);

        $response->assertStatus(422);
    }

    public function test_generate_validates_file_format_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-files/generate', [
                'payment_run_id' => 1,
                'file_format'    => 'invalid_format',
            ]);

        $response->assertStatus(422);
    }

    public function test_generate_validates_payment_run_exists(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-files/generate', [
                'payment_run_id' => 99999,
                'file_format'    => 'sepa_ct',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Download / Submit / Acknowledge
    // -------------------------------------------------------------------------

    public function test_download_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-files/99999/download');

        $response->assertStatus(404);
    }

    public function test_submit_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-files/99999/submit');

        $response->assertStatus(404);
    }

    public function test_acknowledge_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-files/99999/acknowledge');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/payment-files')->assertStatus(401);
    }
}
