<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Services\Accounting\GccWithholdingTaxService;
use Database\Seeders\GccCrossBorderWhtSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class GccWithholdingTaxTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private GccWithholdingTaxService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA'); // Saudi org
        $this->setUpAuthenticatedUser();
        $this->service = app(GccWithholdingTaxService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Seeder
    // ─────────────────────────────────────────────────────────────────────────

    public function test_seeder_creates_codes_for_all_gcc_countries(): void
    {
        (new GccCrossBorderWhtSeeder())->createCodesForOrganization($this->organization->id);

        foreach (['SA', 'AE', 'OM', 'KW', 'BH', 'QA'] as $country) {
            $this->assertDatabaseHas('withholding_tax_codes', [
                'organization_id' => $this->organization->id,
                'code'            => "GCC-XB-{$country}",
                'tax_type'        => 'cross_border_wht',
            ]);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $seeder = new GccCrossBorderWhtSeeder();
        $seeder->createCodesForOrganization($this->organization->id);
        $seeder->createCodesForOrganization($this->organization->id);

        $count = \App\Models\Accounting\WithholdingTaxCode::where('organization_id', $this->organization->id)
            ->where('tax_type', 'cross_border_wht')
            ->count();

        $this->assertEquals(6, $count);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Statutory rates
    // ─────────────────────────────────────────────────────────────────────────

    public function test_statutory_rate_saudi_is_15_percent(): void
    {
        $this->assertEquals(15.0, $this->service->statutoryRate('SA'));
    }

    public function test_statutory_rate_uae_is_zero(): void
    {
        $this->assertEquals(0.0, $this->service->statutoryRate('AE'));
    }

    public function test_statutory_rate_oman_is_10_percent(): void
    {
        $this->assertEquals(10.0, $this->service->statutoryRate('OM'));
    }

    public function test_statutory_rate_kuwait_is_5_percent(): void
    {
        $this->assertEquals(5.0, $this->service->statutoryRate('KW'));
    }

    public function test_statutory_rate_bahrain_is_zero(): void
    {
        $this->assertEquals(0.0, $this->service->statutoryRate('BH'));
    }

    public function test_statutory_rate_qatar_is_5_percent(): void
    {
        $this->assertEquals(5.0, $this->service->statutoryRate('QA'));
    }

    public function test_statutory_rate_returns_null_for_non_gcc(): void
    {
        $this->assertNull($this->service->statutoryRate('US'));
        $this->assertNull($this->service->statutoryRate('IN'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cross-border detection
    // ─────────────────────────────────────────────────────────────────────────

    public function test_sa_org_paying_om_vendor_is_cross_border(): void
    {
        // Org is SA, vendor is OM → cross-border
        $this->assertTrue($this->service->isCrossBorder($this->organization->id, 'OM'));
    }

    public function test_sa_org_paying_sa_vendor_is_not_cross_border(): void
    {
        // Org is SA, vendor is SA → domestic
        $this->assertFalse($this->service->isCrossBorder($this->organization->id, 'SA'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Code resolution
    // ─────────────────────────────────────────────────────────────────────────

    public function test_resolves_correct_code_for_oman_vendor(): void
    {
        (new GccCrossBorderWhtSeeder())->createCodesForOrganization($this->organization->id);

        $code = $this->service->resolveCode($this->organization->id, 'OM');

        $this->assertNotNull($code);
        $this->assertEquals('GCC-XB-OM', $code->code);
        $this->assertEquals('10.0000', $code->rate);
    }

    public function test_resolves_zero_rate_code_for_uae_vendor(): void
    {
        (new GccCrossBorderWhtSeeder())->createCodesForOrganization($this->organization->id);

        $code = $this->service->resolveCode($this->organization->id, 'AE');

        $this->assertNotNull($code);
        $this->assertEquals('0.0000', $code->rate);
    }

    public function test_resolve_returns_null_for_non_gcc_vendor(): void
    {
        (new GccCrossBorderWhtSeeder())->createCodesForOrganization($this->organization->id);

        $code = $this->service->resolveCode($this->organization->id, 'US');

        $this->assertNull($code);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ensureCodesExist
    // ─────────────────────────────────────────────────────────────────────────

    public function test_ensure_codes_exist_seeds_codes(): void
    {
        $this->service->ensureCodesExist($this->organization->id);

        $this->assertDatabaseHas('withholding_tax_codes', [
            'organization_id' => $this->organization->id,
            'code'            => 'GCC-XB-SA',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // applyIfCrossBorder — skips domestic
    // ─────────────────────────────────────────────────────────────────────────

    public function test_apply_if_cross_border_returns_null_for_domestic_payment(): void
    {
        (new GccCrossBorderWhtSeeder())->createCodesForOrganization($this->organization->id);

        $result = $this->service->applyIfCrossBorder([
            'organization_id'    => $this->organization->id,
            'vendor_country_code' => 'SA',       // same as org
            'payment_type'       => 'payment_made',
            'payment_id'         => 1,
            'gross_amount'       => 10000.0,
            'currency_code'      => 'SAR',
            'transaction_date'   => now()->toDateString(),
        ], []);

        $this->assertNull($result);
    }

    public function test_apply_if_cross_border_returns_null_for_zero_rate_uae(): void
    {
        (new GccCrossBorderWhtSeeder())->createCodesForOrganization($this->organization->id);

        $result = $this->service->applyIfCrossBorder([
            'organization_id'    => $this->organization->id,
            'vendor_country_code' => 'AE', // 0% rate — no deduction
            'payment_type'       => 'payment_made',
            'payment_id'         => 1,
            'gross_amount'       => 10000.0,
            'currency_code'      => 'SAR',
            'transaction_date'   => now()->toDateString(),
        ], []);

        $this->assertNull($result);
    }
}
