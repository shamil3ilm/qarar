<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Concerns;

use App\Exceptions\ERP\MissingTenantException;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Core\Organization;
use App\Models\HR\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class BelongsToOrganizationTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * Acting as an authenticated user should auto-populate organization_id on create.
     */
    public function test_creating_model_with_authenticated_user_auto_sets_organization_id(): void
    {
        $this->setUpAuthenticatedUser();
        $this->actingAs($this->user, 'api');

        $period = PayrollPeriod::create([
            'name'         => 'April 2026',
            'start_date'   => '2026-04-01',
            'end_date'     => '2026-04-30',
            'payment_date' => '2026-05-05',
            'status'       => PayrollPeriod::STATUS_OPEN,
        ]);

        $this->assertNotNull($period->id);
        $this->assertEquals($this->user->organization_id, $period->organization_id);
    }

    /**
     * An explicit organization_id can be provided without any authenticated user.
     */
    public function test_creating_model_with_explicit_organization_id_and_no_auth_succeeds(): void
    {
        $this->assertGuest();

        $period = PayrollPeriod::withoutTenantCheck(function () {
            return PayrollPeriod::create([
                'organization_id' => $this->organization->id,
                'name'            => 'March 2026',
                'start_date'      => '2026-03-01',
                'end_date'        => '2026-03-31',
                'payment_date'    => '2026-04-05',
                'status'          => PayrollPeriod::STATUS_OPEN,
            ]);
        }, reason: 'test: explicit org id without auth');

        $this->assertEquals($this->organization->id, $period->organization_id);
    }

    /**
     * Creating without org_id and without auth must throw MissingTenantException.
     */
    public function test_creating_model_without_org_id_and_without_auth_throws_missing_tenant_exception(): void
    {
        $this->assertGuest();

        $this->expectException(MissingTenantException::class);

        PayrollPeriod::create([
            'name'         => 'February 2026',
            'start_date'   => '2026-02-01',
            'end_date'     => '2026-02-28',
            'payment_date' => '2026-03-05',
            'status'       => PayrollPeriod::STATUS_OPEN,
        ]);
    }

    /**
     * withoutTenantCheck() bypasses the guard so a model can be created without auth.
     */
    public function test_without_tenant_check_bypass_allows_creation_without_auth(): void
    {
        $this->assertGuest();

        $period = PayrollPeriod::withoutTenantCheck(function () {
            return PayrollPeriod::create([
                'organization_id' => $this->organization->id,
                'name'            => 'January 2026',
                'start_date'      => '2026-01-01',
                'end_date'        => '2026-01-31',
                'payment_date'    => '2026-02-05',
                'status'          => PayrollPeriod::STATUS_OPEN,
            ]);
        }, reason: 'test');

        $this->assertNotNull($period->id);
        $this->assertEquals($this->organization->id, $period->organization_id);
    }
}
