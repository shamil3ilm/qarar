<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tax;

use App\Models\Core\Organization;
use App\Services\Tax\TaxCalculation;
use App\Services\Tax\TaxCalculatorService;
use App\Services\Tax\TaxService;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Thin subclass that:
 *  - Exposes protected helpers for direct testing.
 *  - Stubs out getTaxCategory() and getVatRate() so tests never hit the database.
 *    VAT tests must supply an explicit tax_rate in line data; the stub returns a
 *    zero DB-rate so the factory falls through to the explicit-rate fallback path.
 *  - Stubs getGstRate() to read from line['gst_rate'] directly.
 */
class TestableTaxCalculatorService extends TaxCalculatorService
{
    /**
     * Never query TaxCategory — return null so the factory uses the explicit tax_rate.
     */
    protected function getTaxCategory(array $line): ?\App\Models\Tax\TaxCategory
    {
        return null;
    }

    /**
     * Return 0 so the factory falls back to the explicit tax_rate on each line.
     */
    protected function getVatRate(string $countryCode, ?\App\Models\Tax\TaxCategory $taxCategory): float
    {
        return 0.0;
    }

    /**
     * Read rate directly from line data — no DB lookup.
     */
    protected function getGstRate(array $line): float
    {
        return (float) ($line['gst_rate'] ?? $line['tax_rate'] ?? 0);
    }

    public function exposeCalculateVat(Organization $organization, array $lines): \App\Services\Tax\TaxResult
    {
        return $this->calculateVat($organization, $lines);
    }

    public function exposeCalculateGst(
        Organization $organization,
        array $lines,
        ?string $placeOfSupply
    ): \App\Services\Tax\TaxResult {
        return $this->calculateGst($organization, $lines, $placeOfSupply);
    }

    public function exposeGetLineSubtotal(array $line): float
    {
        return $this->getLineSubtotal($line);
    }
}

class TaxCalculatorServiceTest extends TestCase
{
    private MockInterface $taxService;
    private TestableTaxCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taxService = Mockery::mock(TaxService::class);
        $this->service = new TestableTaxCalculatorService($this->taxService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a lightweight Organization stub that does not require a bootstrapped
     * Eloquent stack (no encrypter, no DB connection).
     */
    private function makeOrganization(string $taxScheme, string $countryCode = 'SA'): Organization
    {
        return new class($taxScheme, $countryCode) extends Organization {
            public function __construct(
                public string $tax_scheme,
                public string $country_code,
                public string $state_code = '27',
                public string $tax_number = '27AAAAA1234Z1ZX'
            ) {
                // Skip Eloquent boot to avoid encrypter / DB dependencies
            }

            public function __set($key, $value): void
            {
                $this->$key = $value;
            }

            public function __get($key)
            {
                return $this->$key ?? null;
            }
        };
    }

    /**
     * Build a TaxCalculation stub returned by TaxService.
     */
    private function makeTaxCalc(string $taxableAmount, string $taxAmount): TaxCalculation
    {
        return new TaxCalculation(
            taxableAmount: $taxableAmount,
            taxAmount: $taxAmount,
            totalAmount: bcadd($taxableAmount, $taxAmount, 4),
            taxRate: '5',
            isTaxInclusive: false
        );
    }

    // =========================================================================
    // calculate() dispatch tests
    // =========================================================================

    public function test_calculate_routes_to_calculateVat_when_tax_scheme_is_VAT(): void
    {
        $org = $this->makeOrganization('VAT');

        // TaxService will be called for the line (tax_rate > 0)
        $this->taxService
            ->shouldReceive('calculateTaxOnExclusive')
            ->once()
            ->andReturn($this->makeTaxCalc('100', '5'));

        $lines = [
            ['quantity' => 1, 'unit_price' => 100, 'tax_rate' => 5],
        ];

        $result = $this->service->calculate($org, $lines);

        // VAT result does not have isGst flag set
        $this->assertFalse($result->isGst);
    }

    public function test_calculate_routes_to_calculateGst_when_tax_scheme_is_GST(): void
    {
        $org = $this->makeOrganization('GST', 'IN');
        $org->state_code = '27';

        $this->taxService
            ->shouldReceive('calculateTaxOnExclusive')
            ->once()
            ->andReturn($this->makeTaxCalc('100', '18'));

        $lines = [
            ['quantity' => 1, 'unit_price' => 100, 'gst_rate' => 18],
        ];

        $result = $this->service->calculate($org, $lines, '29');

        $this->assertTrue($result->isGst);
    }

    public function test_calculate_routes_to_noTax_when_tax_scheme_is_anything_else(): void
    {
        $org = $this->makeOrganization('NONE');

        // TaxService must NOT be called for no-tax scheme
        $this->taxService->shouldNotReceive('calculateTaxOnExclusive');

        $lines = [
            ['quantity' => 2, 'unit_price' => 50],
        ];

        $result = $this->service->calculate($org, $lines);

        $this->assertFalse($result->isGst);
        $this->assertEqualsWithDelta(0.0, (float) $result->totalTaxAmount, 0.0001);
    }

    public function test_calculate_throws_InvalidArgumentException_when_lines_array_is_empty(): void
    {
        $org = $this->makeOrganization('VAT');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lines array cannot be empty.');

        $this->service->calculate($org, []);
    }

    // =========================================================================
    // calculateVat() arithmetic tests
    // =========================================================================

    public function test_calculateVat_calls_calculateTaxOnExclusive_for_each_line_with_positive_tax_rate(): void
    {
        $org = $this->makeOrganization('VAT');

        $this->taxService
            ->shouldReceive('calculateTaxOnExclusive')
            ->twice()
            ->andReturn($this->makeTaxCalc('100', '5'));

        $lines = [
            ['quantity' => 1, 'unit_price' => 100, 'tax_rate' => 5],
            ['quantity' => 2, 'unit_price' => 50, 'tax_rate' => 5],
        ];

        $this->service->exposeCalculateVat($org, $lines);

        // ->twice() is the assertion; make PHPUnit aware.
        $this->addToAssertionCount(1);
    }

    public function test_calculateVat_does_not_call_taxService_when_tax_rate_is_zero(): void
    {
        $org = $this->makeOrganization('VAT');

        // TaxCategory lookup also returns null in unit tests (no DB), so tax_rate is 0
        $this->taxService->shouldNotReceive('calculateTaxOnExclusive');

        $lines = [
            ['quantity' => 1, 'unit_price' => 200, 'tax_rate' => 0],
        ];

        $result = $this->service->exposeCalculateVat($org, $lines);

        $this->assertEqualsWithDelta(0.0, (float) $result->totalTaxAmount, 0.0001);
    }

    public function test_calculateVat_throws_InvalidArgumentException_when_explicit_tax_rate_exceeds_100(): void
    {
        $org = $this->makeOrganization('VAT');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax rate must be between 0 and 100');

        $lines = [
            ['quantity' => 1, 'unit_price' => 100, 'tax_rate' => 150],
        ];

        $this->service->exposeCalculateVat($org, $lines);
    }

    public function test_calculateVat_throws_InvalidArgumentException_when_explicit_tax_rate_is_negative(): void
    {
        $org = $this->makeOrganization('VAT');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax rate must be between 0 and 100');

        $lines = [
            ['quantity' => 1, 'unit_price' => 100, 'tax_rate' => -1],
        ];

        $this->service->exposeCalculateVat($org, $lines);
    }

    public function test_calculateVat_result_has_correct_totalTaxableAmount_and_totalTaxAmount(): void
    {
        $org = $this->makeOrganization('VAT');

        $this->taxService
            ->shouldReceive('calculateTaxOnExclusive')
            ->andReturn($this->makeTaxCalc('200', '10'));

        $lines = [
            ['quantity' => 2, 'unit_price' => 100, 'tax_rate' => 5],
        ];

        $result = $this->service->exposeCalculateVat($org, $lines);

        $this->assertEqualsWithDelta(200.0, (float) $result->totalTaxableAmount, 0.0001);
        $this->assertEqualsWithDelta(10.0, (float) $result->totalTaxAmount, 0.0001);
    }

    public function test_calculateVat_tax_summary_groups_lines_by_rate(): void
    {
        $org = $this->makeOrganization('VAT');

        $this->taxService
            ->shouldReceive('calculateTaxOnExclusive')
            ->andReturn($this->makeTaxCalc('100', '5'));

        $lines = [
            ['quantity' => 1, 'unit_price' => 100, 'tax_rate' => 5],
            ['quantity' => 1, 'unit_price' => 100, 'tax_rate' => 5],
        ];

        $result = $this->service->exposeCalculateVat($org, $lines);

        // Both lines share the same tax_rate so summary should have 1 entry
        $this->assertCount(1, $result->taxSummary);
    }

    // =========================================================================
    // calculateGst() inter-state tests
    // =========================================================================

    public function test_calculateGst_interState_calls_calculateTaxOnExclusive_once_for_igst(): void
    {
        $org = $this->makeOrganization('GST', 'IN');
        $org->state_code = '27'; // Maharashtra

        $this->taxService
            ->shouldReceive('calculateTaxOnExclusive')
            ->once()
            ->andReturn($this->makeTaxCalc('1000', '180'));

        $lines = [
            ['quantity' => 1, 'unit_price' => 1000, 'gst_rate' => 18],
        ];

        // placeOfSupply = '29' (Karnataka) — inter-state
        $result = $this->service->exposeCalculateGst($org, $lines, '29');

        $this->assertTrue($result->isInterState);
    }

    public function test_calculateGst_interState_result_has_isInterState_true(): void
    {
        $org = $this->makeOrganization('GST', 'IN');
        $org->state_code = '27';

        $this->taxService
            ->shouldReceive('calculateTaxOnExclusive')
            ->andReturn($this->makeTaxCalc('500', '45'));

        $lines = [
            ['quantity' => 1, 'unit_price' => 500, 'gst_rate' => 9],
        ];

        $result = $this->service->exposeCalculateGst($org, $lines, '29');

        $this->assertTrue($result->isInterState);
    }

    public function test_calculateGst_interState_igst_amount_is_in_result_lines(): void
    {
        $org = $this->makeOrganization('GST', 'IN');
        $org->state_code = '27';

        $this->taxService
            ->shouldReceive('calculateTaxOnExclusive')
            ->andReturn($this->makeTaxCalc('1000', '180'));

        $lines = [
            ['quantity' => 1, 'unit_price' => 1000, 'gst_rate' => 18],
        ];

        $result = $this->service->exposeCalculateGst($org, $lines, '29');

        $this->assertArrayHasKey('igst_amount', $result->lines[0]);
        $this->assertEqualsWithDelta(180.0, (float) $result->lines[0]['igst_amount'], 0.0001);
    }

    // =========================================================================
    // calculateGst() intra-state tests
    // =========================================================================

    public function test_calculateGst_intraState_calls_calculateTaxOnExclusive_once_for_cgst_at_half_rate(): void
    {
        $org = $this->makeOrganization('GST', 'IN');
        $org->state_code = '27';

        // For intra-state the factory calls calculateTaxOnExclusive once with halfRate,
        // then reuses the result for SGST.
        $this->taxService
            ->shouldReceive('calculateTaxOnExclusive')
            ->once()
            ->andReturn($this->makeTaxCalc('1000', '90'));

        $lines = [
            ['quantity' => 1, 'unit_price' => 1000, 'gst_rate' => 18],
        ];

        // Same state code = intra-state
        $result = $this->service->exposeCalculateGst($org, $lines, '27');

        $this->assertFalse($result->isInterState);
    }

    public function test_calculateGst_intraState_result_has_isInterState_false(): void
    {
        $org = $this->makeOrganization('GST', 'IN');
        $org->state_code = '27';

        $this->taxService
            ->shouldReceive('calculateTaxOnExclusive')
            ->andReturn($this->makeTaxCalc('500', '45'));

        $lines = [
            ['quantity' => 1, 'unit_price' => 500, 'gst_rate' => 18],
        ];

        $result = $this->service->exposeCalculateGst($org, $lines, '27');

        $this->assertFalse($result->isInterState);
    }

    public function test_calculateGst_intraState_cgst_and_sgst_amounts_are_in_result_lines(): void
    {
        $org = $this->makeOrganization('GST', 'IN');
        $org->state_code = '27';

        $this->taxService
            ->shouldReceive('calculateTaxOnExclusive')
            ->andReturn($this->makeTaxCalc('1000', '90'));

        $lines = [
            ['quantity' => 1, 'unit_price' => 1000, 'gst_rate' => 18],
        ];

        $result = $this->service->exposeCalculateGst($org, $lines, '27');

        $line = $result->lines[0];
        $this->assertArrayHasKey('cgst_amount', $line);
        $this->assertArrayHasKey('sgst_amount', $line);
        // SGST = CGST (same amount as returned from the single taxService call)
        $this->assertEqualsWithDelta((float) $line['cgst_amount'], (float) $line['sgst_amount'], 0.0001);
    }

    // =========================================================================
    // extractTaxFromInclusive() tests
    // =========================================================================

    public function test_extractTaxFromInclusive_delegates_to_taxService_extractTaxFromInclusive(): void
    {
        $fakeCalc = new TaxCalculation(
            taxableAmount: '952.3810',
            taxAmount: '47.6190',
            totalAmount: '1000',
            taxRate: '5',
            isTaxInclusive: true
        );

        $this->taxService
            ->shouldReceive('extractTaxFromInclusive')
            ->once()
            ->with('1000', '5')
            ->andReturn($fakeCalc);

        $result = $this->service->extractTaxFromInclusive(1000.0, 5.0);

        $this->assertArrayHasKey('base_amount', $result);
        $this->assertArrayHasKey('tax_amount', $result);
        $this->assertArrayHasKey('tax_rate', $result);
    }

    public function test_extractTaxFromInclusive_returns_correct_keys_with_numeric_values(): void
    {
        $fakeCalc = new TaxCalculation(
            taxableAmount: '952.3810',
            taxAmount: '47.6190',
            totalAmount: '1000',
            taxRate: '5',
            isTaxInclusive: true
        );

        $this->taxService
            ->shouldReceive('extractTaxFromInclusive')
            ->andReturn($fakeCalc);

        $result = $this->service->extractTaxFromInclusive(1000.0, 5.0);

        $this->assertEqualsWithDelta(952.381, $result['base_amount'], 0.001);
        $this->assertEqualsWithDelta(47.619, $result['tax_amount'], 0.001);
        $this->assertSame(5.0, $result['tax_rate']);
    }

    // =========================================================================
    // getLineSubtotal() validation tests
    // =========================================================================

    public function test_getLineSubtotal_throws_InvalidArgumentException_when_quantity_is_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line quantity must be positive.');

        $this->service->exposeGetLineSubtotal([
            'quantity'   => 0,
            'unit_price' => 100,
        ]);
    }

    public function test_getLineSubtotal_throws_InvalidArgumentException_when_quantity_is_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line quantity must be positive.');

        $this->service->exposeGetLineSubtotal([
            'quantity'   => -2,
            'unit_price' => 50,
        ]);
    }

    public function test_getLineSubtotal_throws_InvalidArgumentException_when_discount_exceeds_gross(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount amount cannot exceed line gross amount.');

        $this->service->exposeGetLineSubtotal([
            'quantity'        => 1,
            'unit_price'      => 100,
            'discount_amount' => 101,
        ]);
    }

    public function test_getLineSubtotal_returns_correct_subtotal_for_valid_line(): void
    {
        $subtotal = $this->service->exposeGetLineSubtotal([
            'quantity'        => 3,
            'unit_price'      => 100,
            'discount_amount' => 30,
        ]);

        // 3 * 100 - 30 = 270
        $this->assertEqualsWithDelta(270.0, $subtotal, 0.0001);
    }

    public function test_getLineSubtotal_returns_correct_subtotal_with_no_discount(): void
    {
        $subtotal = $this->service->exposeGetLineSubtotal([
            'quantity'   => 5,
            'unit_price' => 20,
        ]);

        $this->assertEqualsWithDelta(100.0, $subtotal, 0.0001);
    }

    public function test_getLineSubtotal_defaults_quantity_to_one_when_not_provided(): void
    {
        $subtotal = $this->service->exposeGetLineSubtotal([
            'unit_price' => 250,
        ]);

        $this->assertEqualsWithDelta(250.0, $subtotal, 0.0001);
    }
}
