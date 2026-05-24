<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\WithholdingTaxCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class WithholdingTaxTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.wht.view',
            'accounting.wht.manage',
            'accounting.wht.post',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCode(array $overrides = []): WithholdingTaxCode
    {
        return WithholdingTaxCode::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'WHT-' . fake()->unique()->numerify('##'),
            'name'            => 'Test WHT Code',
            'applicable_to'   => 'supplier',
            'rate'            => 5.00,
            'is_active'       => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index Codes
    // -------------------------------------------------------------------------

    public function test_index_codes_returns_list(): void
    {
        $this->makeCode();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/withholding-tax/codes');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store Code
    // -------------------------------------------------------------------------

    public function test_store_code_creates_wht_code(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/withholding-tax/codes', [
                'code'          => 'WHT15',
                'name'          => '15% WHT Supplier',
                'applicable_to' => 'supplier',
                'rate'          => 15.00,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_code_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/withholding-tax/codes', []);

        $response->assertStatus(422);
    }

    public function test_store_code_validates_applicable_to_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/withholding-tax/codes', [
                'code'          => 'XX',
                'name'          => 'Test',
                'applicable_to' => 'invalid',
                'rate'          => 5.00,
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show Code
    // -------------------------------------------------------------------------

    public function test_show_code_returns_details(): void
    {
        $code = $this->makeCode();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/withholding-tax/codes/' . $code->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_code_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/withholding-tax/codes/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update Code
    // -------------------------------------------------------------------------

    public function test_update_code_modifies_wht_code(): void
    {
        $code = $this->makeCode(['rate' => 5.00]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/withholding-tax/codes/' . $code->uuid, [
                'rate' => 10.00,
            ]);

        $response->assertStatus(200);
        $this->assertEquals(10.00, (float) $code->fresh()->rate);
    }

    // -------------------------------------------------------------------------
    // Destroy Code
    // -------------------------------------------------------------------------

    public function test_destroy_code_deletes_wht_code(): void
    {
        $code = $this->makeCode();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/withholding-tax/codes/' . $code->uuid);

        $response->assertStatus(200);
        $this->assertNull(WithholdingTaxCode::find($code->id));
    }

    // -------------------------------------------------------------------------
    // Calculate
    // -------------------------------------------------------------------------

    public function test_calculate_validates_gross_amount_required(): void
    {
        $code = $this->makeCode();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/withholding-tax/codes/' . $code->uuid . '/calculate', []);

        $response->assertStatus(422);
    }

    public function test_calculate_returns_computed_amounts(): void
    {
        $code = $this->makeCode(['rate' => 10.00]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/withholding-tax/codes/' . $code->uuid . '/calculate', [
                'gross_amount' => 1000.00,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Summary
    // -------------------------------------------------------------------------

    public function test_summary_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/withholding-tax/summary');

        $response->assertStatus(422);
    }

    public function test_summary_returns_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/withholding-tax/summary?from=2025-01-01&to=2025-12-31');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/withholding-tax/codes')->assertStatus(401);
    }
}
