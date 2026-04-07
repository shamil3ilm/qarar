<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Core\Organization;
use App\Models\HR\SocialInsuranceScheme;
use Illuminate\Database\Seeder;

class SocialInsuranceSchemesSeeder extends Seeder
{
    /**
     * Seed default GCC social insurance schemes for all (or one) organization.
     * Follows the same pattern as ChartOfAccountsSeeder.
     */
    public function run(?int $organizationId = null): void
    {
        $organizations = $organizationId
            ? Organization::where('id', $organizationId)->get()
            : Organization::all();

        foreach ($organizations as $org) {
            $this->createSchemesForOrganization($org->id);
            $this->command?->info("Social insurance schemes seeded for: {$org->name}");
        }
    }

    public function createSchemesForOrganization(int $organizationId): void
    {
        foreach ($this->schemeTemplates() as $template) {
            SocialInsuranceScheme::firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'country_code'    => $template['country_code'],
                    'scheme_code'     => $template['scheme_code'],
                ],
                array_merge($template, ['organization_id' => $organizationId])
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function schemeTemplates(): array
    {
        return [
            // ─── Saudi Arabia ────────────────────────────────────────────────
            [
                'country_code'             => 'SA',
                'scheme_code'              => 'GOSI',
                'name'                     => 'Saudi GOSI — General Organization for Social Insurance',
                'employee_contribution_pct' => '9.00',
                'employer_contribution_pct' => '9.00',
                'work_hazard_pct'           => '2.00',
                'applicable_to'             => 'nationals_only',
                'salary_ceiling'            => '45000.0000',
                'salary_floor'              => '400.0000',
                'is_active'                 => true,
            ],

            // ─── Oman ─────────────────────────────────────────────────────────
            [
                'country_code'             => 'OM',
                'scheme_code'              => 'PASI',
                'name'                     => 'Oman PASI — Public Authority for Social Insurance',
                'employee_contribution_pct' => '7.00',
                'employer_contribution_pct' => '10.50',
                'work_hazard_pct'           => '1.00',
                'applicable_to'             => 'nationals_only',
                'salary_ceiling'            => '3000.0000',
                'salary_floor'              => '270.0000',
                'is_active'                 => true,
            ],

            // ─── Kuwait ───────────────────────────────────────────────────────
            [
                'country_code'             => 'KW',
                'scheme_code'              => 'PIFSS',
                'name'                     => 'Kuwait PIFSS — Public Institution for Social Security',
                'employee_contribution_pct' => '7.50',
                'employer_contribution_pct' => '11.50',
                'work_hazard_pct'           => '0.00',
                'applicable_to'             => 'nationals_only',
                'salary_ceiling'            => '2750.0000',
                'salary_floor'              => '0.0000',
                'is_active'                 => true,
            ],

            // ─── Bahrain (Nationals) ──────────────────────────────────────────
            [
                'country_code'             => 'BH',
                'scheme_code'              => 'SIO_NATIONALS',
                'name'                     => 'Bahrain SIO — Social Insurance Organisation (Nationals)',
                'employee_contribution_pct' => '6.00',
                'employer_contribution_pct' => '9.00',
                'work_hazard_pct'           => '3.00',
                'applicable_to'             => 'nationals_only',
                'salary_ceiling'            => '4000.0000',
                'salary_floor'              => '0.0000',
                'is_active'                 => true,
            ],

            // ─── Bahrain (Expats) ─────────────────────────────────────────────
            [
                'country_code'             => 'BH',
                'scheme_code'              => 'SIO_EXPATS',
                'name'                     => 'Bahrain SIO — Social Insurance Organisation (Expats)',
                'employee_contribution_pct' => '1.00',
                'employer_contribution_pct' => '3.00',
                'work_hazard_pct'           => '0.00',
                'applicable_to'             => 'expats_only',
                'salary_ceiling'            => '4000.0000',
                'salary_floor'              => '0.0000',
                'is_active'                 => true,
            ],

            // ─── Qatar ────────────────────────────────────────────────────────
            [
                'country_code'             => 'QA',
                'scheme_code'              => 'GRSIA',
                'name'                     => 'Qatar GRSIA — General Retirement & Social Insurance Authority',
                'employee_contribution_pct' => '5.00',
                'employer_contribution_pct' => '10.00',
                'work_hazard_pct'           => '0.00',
                'applicable_to'             => 'nationals_only',
                'salary_ceiling'            => '100000.0000',
                'salary_floor'              => '0.0000',
                'is_active'                 => true,
            ],

            // ─── UAE ──────────────────────────────────────────────────────────
            [
                'country_code'             => 'AE',
                'scheme_code'              => 'GPSSA',
                'name'                     => 'UAE GPSSA — General Pension & Social Security Authority',
                'employee_contribution_pct' => '5.00',
                'employer_contribution_pct' => '12.50',
                'work_hazard_pct'           => '0.00',
                'applicable_to'             => 'nationals_only',
                'salary_ceiling'            => null,
                'salary_floor'              => '0.0000',
                'is_active'                 => true,
            ],
        ];
    }
}
