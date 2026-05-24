<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Accounting\WithholdingTaxCode;
use App\Models\Core\Organization;
use Illuminate\Database\Seeder;

/**
 * Seeds GCC cross-border Withholding Tax codes per organization.
 *
 * Statutory rates (applies when the paying org is in the vendor's destination country):
 *   SA — 15% on technical services / royalties (ZATCA Circular)
 *   AE — 0%  (no WHT regime)
 *   OM — 10% on dividends, interest, royalties, management fees
 *   KW — 5%  (limited WHT in specific circumstances)
 *   BH — 0%  (no WHT regime)
 *   QA — 5%  on dividends to non-residents
 *
 * Idempotent via firstOrCreate. Does not hard-code GL account IDs.
 */
class GccCrossBorderWhtSeeder extends Seeder
{
    /**
     * GCC cross-border WHT codes.
     * key = country_code (ISO-3166 alpha-2), value = [rate, description]
     */
    private const CODES = [
        'SA' => ['rate' => 15.00, 'name' => 'Saudi Arabia Cross-Border WHT',     'description' => 'ZATCA 15% WHT on technical services, royalties, and management fees paid to non-residents.'],
        'AE' => ['rate' =>  0.00, 'name' => 'UAE Cross-Border WHT (Zero-Rate)',   'description' => 'UAE has no withholding tax regime. Zero-rate code for documentation purposes.'],
        'OM' => ['rate' => 10.00, 'name' => 'Oman Cross-Border WHT',              'description' => 'Oman 10% WHT on dividends, interest, royalties and management fees to non-residents.'],
        'KW' => ['rate' =>  5.00, 'name' => 'Kuwait Cross-Border WHT',            'description' => 'Kuwait 5% WHT in specific circumstances for non-resident payments.'],
        'BH' => ['rate' =>  0.00, 'name' => 'Bahrain Cross-Border WHT (Zero-Rate)', 'description' => 'Bahrain has no WHT regime. Zero-rate code for documentation purposes.'],
        'QA' => ['rate' =>  5.00, 'name' => 'Qatar Cross-Border WHT',             'description' => 'Qatar 5% WHT on dividends to non-resident shareholders.'],
    ];

    public function run(): void
    {
        Organization::all()->each(
            fn (Organization $org) => $this->createCodesForOrganization($org->id)
        );
    }

    public function createCodesForOrganization(int $organizationId): void
    {
        foreach (self::CODES as $countryCode => $config) {
            WithholdingTaxCode::firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'code'            => "GCC-XB-{$countryCode}",
                ],
                [
                    'name'            => $config['name'],
                    'description'     => $config['description'],
                    'applicable_to'   => WithholdingTaxCode::APPLICABLE_SUPPLIER,
                    'rate'            => $config['rate'],
                    'country_code'    => $countryCode,
                    'tax_type'        => 'cross_border_wht',
                    'is_active'       => true,
                ]
            );
        }
    }
}
