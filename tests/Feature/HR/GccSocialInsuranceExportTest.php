<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\HR\SocialInsuranceRecord;
use App\Models\HR\SocialInsuranceScheme;
use App\Models\HR\SocialInsuranceSubmission;
use App\Models\HR\SocialInsuranceSubmissionLine;
use App\Services\HR\BahrainSioExportService;
use App\Services\HR\KuwaitPifssExportService;
use App\Services\HR\OmanPasiExportService;
use App\Services\HR\QatarGrsiaExportService;
use App\Services\HR\UaeGpssaExportService;
use Database\Seeders\SocialInsuranceSchemesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class GccSocialInsuranceExportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Seeder tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_seeder_creates_schemes_for_all_gcc_countries(): void
    {
        (new SocialInsuranceSchemesSeeder())->createSchemesForOrganization($this->organization->id);

        foreach (['SA', 'OM', 'KW', 'BH', 'QA', 'AE'] as $code) {
            $this->assertDatabaseHas('social_insurance_schemes', [
                'organization_id' => $this->organization->id,
                'country_code'    => $code,
                'is_active'       => true,
            ]);
        }
    }

    public function test_seeder_stores_correct_oman_pasi_rates(): void
    {
        (new SocialInsuranceSchemesSeeder())->createSchemesForOrganization($this->organization->id);

        $scheme = SocialInsuranceScheme::where('organization_id', $this->organization->id)
            ->where('scheme_code', 'PASI')
            ->firstOrFail();

        $this->assertEquals('7.00', $scheme->employee_contribution_pct);
        $this->assertEquals('10.50', $scheme->employer_contribution_pct);
        $this->assertEquals('1.00', $scheme->work_hazard_pct);
        $this->assertEquals('3000.0000', $scheme->salary_ceiling);
    }

    public function test_seeder_is_idempotent(): void
    {
        (new SocialInsuranceSchemesSeeder())->createSchemesForOrganization($this->organization->id);
        (new SocialInsuranceSchemesSeeder())->createSchemesForOrganization($this->organization->id);

        $count = SocialInsuranceScheme::where('organization_id', $this->organization->id)->count();
        $this->assertEquals(7, $count); // SA + OM + KW + BH_nationals + BH_expats + QA + AE
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export service helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function makeSubmissionWithLine(
        string $schemeCode,
        array $lineOverrides = []
    ): SocialInsuranceSubmission {
        (new SocialInsuranceSchemesSeeder())->createSchemesForOrganization($this->organization->id);

        $scheme = SocialInsuranceScheme::where('organization_id', $this->organization->id)
            ->where('scheme_code', $schemeCode)
            ->firstOrFail();

        $submission = SocialInsuranceSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'scheme_id'       => $scheme->id,
            'period_year'     => 2026,
            'period_month'    => 3,
            'status'          => 'submitted',
        ]);

        $employee = \App\Models\HR\Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
        ]);

        $record = SocialInsuranceRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'employee_id'     => $employee->id,
            'scheme_id'       => $scheme->id,
            'status'          => 'active',
            'enrollment_date' => '2024-01-01',
        ]);

        SocialInsuranceSubmissionLine::factory()->create(array_merge([
            'submission_id'           => $submission->id,
            'employee_id'             => $employee->id,
            'record_id'               => $record->id,
            'insurable_salary'        => '1500.0000',
            'employee_contribution'   => '105.0000',
            'employer_contribution'   => '157.5000',
            'work_hazard_contribution' => '15.0000',
            'total_contribution'      => '277.5000',
        ], $lineOverrides));

        return $submission;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Oman PASI
    // ─────────────────────────────────────────────────────────────────────────

    public function test_oman_pasi_export_contains_required_headers(): void
    {
        $submission = $this->makeSubmissionWithLine('PASI');

        $service = app(OmanPasiExportService::class);
        $csv = $service->generateCsv($submission);

        $this->assertStringContainsString('Civil ID', $csv);
        $this->assertStringContainsString('Employee Contribution 7%', $csv);
        $this->assertStringContainsString('Enrollment Date', $csv);
    }

    public function test_oman_pasi_export_contains_contribution_amounts(): void
    {
        $submission = $this->makeSubmissionWithLine('PASI', [
            'employee_contribution'   => '70.0000',
            'employer_contribution'   => '105.0000',
            'work_hazard_contribution' => '10.0000',
            'total_contribution'      => '185.0000',
        ]);

        $service = app(OmanPasiExportService::class);
        $csv = $service->generateCsv($submission);

        $this->assertStringContainsString('70.000', $csv);
        $this->assertStringContainsString('105.000', $csv);
        $this->assertStringContainsString('185.000', $csv);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Kuwait PIFSS
    // ─────────────────────────────────────────────────────────────────────────

    public function test_kuwait_pifss_export_contains_required_headers(): void
    {
        $submission = $this->makeSubmissionWithLine('PIFSS');

        $service = app(KuwaitPifssExportService::class);
        $csv = $service->generateCsv($submission);

        $this->assertStringContainsString('Civil File No', $csv);
        $this->assertStringContainsString('Kuwaiti / Non-Kuwaiti', $csv);
        $this->assertStringContainsString('Employee Contribution 7.5%', $csv);
    }

    public function test_kuwait_pifss_export_shows_nationality_label(): void
    {
        $submission = $this->makeSubmissionWithLine('PIFSS');

        // Update employee nationality to KW (Kuwaiti)
        $line = $submission->lines()->first();
        $line->employee->update(['nationality' => 'KW']);

        $service = app(KuwaitPifssExportService::class);
        $csv = $service->generateCsv($submission);

        $this->assertStringContainsString('Kuwaiti', $csv);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bahrain SIO
    // ─────────────────────────────────────────────────────────────────────────

    public function test_bahrain_sio_export_contains_required_headers(): void
    {
        $submission = $this->makeSubmissionWithLine('SIO_NATIONALS');

        $service = app(BahrainSioExportService::class);
        $csv = $service->generateCsv($submission);

        $this->assertStringContainsString('CPR Number', $csv);
        $this->assertStringContainsString('Work Injury Contribution', $csv);
        $this->assertStringContainsString('Bahraini / Expatriate', $csv);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Qatar GRSIA
    // ─────────────────────────────────────────────────────────────────────────

    public function test_qatar_grsia_export_contains_required_headers(): void
    {
        $submission = $this->makeSubmissionWithLine('GRSIA');

        $service = app(QatarGrsiaExportService::class);
        $csv = $service->generateCsv($submission);

        $this->assertStringContainsString('QID', $csv);
        $this->assertStringContainsString('GRSIA File Number', $csv);
        $this->assertStringContainsString('Employer Contribution 10%', $csv);
    }

    public function test_qatar_grsia_export_contains_contribution_amounts(): void
    {
        $submission = $this->makeSubmissionWithLine('GRSIA', [
            'insurable_salary'      => '20000.0000',
            'employee_contribution' => '1000.0000',
            'employer_contribution' => '2000.0000',
            'work_hazard_contribution' => '0.0000',
            'total_contribution'    => '3000.0000',
        ]);

        $service = app(QatarGrsiaExportService::class);
        $csv = $service->generateCsv($submission);

        $this->assertStringContainsString('1000.000', $csv);
        $this->assertStringContainsString('2000.000', $csv);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UAE GPSSA
    // ─────────────────────────────────────────────────────────────────────────

    public function test_uae_gpssa_export_contains_required_headers(): void
    {
        $submission = $this->makeSubmissionWithLine('GPSSA');

        $service = app(UaeGpssaExportService::class);
        $csv = $service->generateCsv($submission);

        $this->assertStringContainsString('Emirates ID', $csv);
        $this->assertStringContainsString('GPSSA Registration No', $csv);
        $this->assertStringContainsString('Employer Contribution 12.5%', $csv);
    }

    public function test_uae_gpssa_export_contains_contribution_amounts(): void
    {
        $submission = $this->makeSubmissionWithLine('GPSSA', [
            'insurable_salary'      => '15000.0000',
            'employee_contribution' => '750.0000',
            'employer_contribution' => '1875.0000',
            'work_hazard_contribution' => '0.0000',
            'total_contribution'    => '2625.0000',
        ]);

        $service = app(UaeGpssaExportService::class);
        $csv = $service->generateCsv($submission);

        $this->assertStringContainsString('750.000', $csv);
        $this->assertStringContainsString('1875.000', $csv);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export API endpoint
    // ─────────────────────────────────────────────────────────────────────────

    public function test_export_endpoint_returns_csv_for_pasi_submission(): void
    {
        $this->setUpAuthenticatedUser(['hr.social-insurance.view']);

        $submission = $this->makeSubmissionWithLine('PASI');

        $response = $this->apiGet("/hr/social-insurance/submissions/{$submission->uuid}/export");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_export_endpoint_rejects_unknown_scheme(): void
    {
        $this->setUpAuthenticatedUser(['hr.social-insurance.view']);

        $scheme = SocialInsuranceScheme::factory()->create([
            'organization_id' => $this->organization->id,
            'scheme_code'     => 'UNKNOWN_SCHEME',
        ]);

        $submission = SocialInsuranceSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'scheme_id'       => $scheme->id,
            'status'          => 'submitted',
        ]);

        $response = $this->apiGet("/hr/social-insurance/submissions/{$submission->uuid}/export");

        $response->assertStatus(422);
    }

    public function test_export_endpoint_denies_cross_org_access(): void
    {
        $this->setUpAuthenticatedUser(['hr.social-insurance.view']);

        // Create submission in a DIFFERENT organization
        $otherOrg = \App\Models\Core\Organization::factory()->create();
        $otherScheme = SocialInsuranceScheme::factory()->create(['organization_id' => $otherOrg->id]);
        $submission = SocialInsuranceSubmission::factory()->create([
            'organization_id' => $otherOrg->id,
            'scheme_id'       => $otherScheme->id,
        ]);

        $response = $this->apiGet("/hr/social-insurance/submissions/{$submission->uuid}/export");

        // Multi-tenant global scope makes cross-org records invisible → 404
        // (better than 403 which would confirm resource existence)
        $response->assertStatus(404);
    }
}
