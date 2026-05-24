<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccrualDeferral;
use App\Models\Accounting\Account;
use App\Models\Accounting\Currency;
use App\Models\Accounting\FiscalYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AccrualDeferralTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private string $baseUrl = '/api/v1/accruals-deferrals';

    private Account $debitAccount;
    private Account $creditAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();

        Currency::firstOrCreate(
            ['code' => 'SAR'],
            ['name' => 'Saudi Riyal', 'symbol' => 'SAR', 'decimal_places' => 2, 'is_active' => true]
        );

        FiscalYear::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'name'            => 'FY 2026',
            'start_date'      => '2026-01-01',
            'end_date'        => '2026-12-31',
            'is_current'      => true,
            'is_closed'       => false,
        ]);

        $this->debitAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'code'            => '1001',
            'name'            => 'Prepaid Expenses',
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => Account::SUBTYPE_CASH,
            'currency_code'   => 'SAR',
            'is_active'       => true,
            'is_system'       => false,
            'is_header'       => false,
            'level'           => 1,
        ]);

        $this->creditAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'code'            => '5001',
            'name'            => 'Rent Expense',
            'account_type'    => Account::TYPE_EXPENSE,
            'sub_type'        => Account::SUBTYPE_OPERATING_EXPENSE,
            'currency_code'   => 'SAR',
            'is_active'       => true,
            'is_system'       => false,
            'is_header'       => false,
            'level'           => 1,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'reference'         => 'AD-0001',
            'type'              => AccrualDeferral::TYPE_ACCRUAL,
            'debit_account_id'  => $this->debitAccount->id,
            'credit_account_id' => $this->creditAccount->id,
            'total_amount'      => 12000.00,
            'currency_code'     => 'SAR',
            'start_date'        => '2026-01-01',
            'end_date'          => '2026-12-31',
            'periods_total'     => 12,
            'description'       => 'Annual office rent spread over 12 months',
        ], $overrides);
    }

    private function makeEntry(array $overrides = []): AccrualDeferral
    {
        return AccrualDeferral::withoutGlobalScopes()->create(array_merge([
            'organization_id'   => $this->organization->id,
            'reference'         => 'AD-0001',
            'type'              => AccrualDeferral::TYPE_ACCRUAL,
            'debit_account_id'  => $this->debitAccount->id,
            'credit_account_id' => $this->creditAccount->id,
            'total_amount'      => 12000.00,
            'per_period_amount' => 1000.00,
            'currency_code'     => 'SAR',
            'start_date'        => '2026-01-01',
            'end_date'          => '2026-12-31',
            'periods_total'     => 12,
            'periods_posted'    => 0,
            'status'            => AccrualDeferral::STATUS_ACTIVE,
            'created_by'        => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_can_list_accrual_deferral_entries(): void
    {
        $this->makeEntry(['reference' => 'AD-0001', 'type' => AccrualDeferral::TYPE_ACCRUAL]);
        $this->makeEntry(['reference' => 'AD-0002', 'type' => AccrualDeferral::TYPE_DEFERRAL]);

        $response = $this->withToken($this->token)
            ->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_list_filters_by_type(): void
    {
        $this->makeEntry(['reference' => 'AD-A', 'type' => AccrualDeferral::TYPE_ACCRUAL]);
        $this->makeEntry(['reference' => 'AD-D', 'type' => AccrualDeferral::TYPE_DEFERRAL]);

        $response = $this->withToken($this->token)
            ->getJson("{$this->baseUrl}?type=accrual");

        $response->assertStatus(200);
        foreach ($response->json('data') as $item) {
            $this->assertSame('accrual', $item['type']);
        }
    }

    public function test_list_filters_by_status(): void
    {
        $this->makeEntry(['reference' => 'AD-ACT', 'status' => AccrualDeferral::STATUS_ACTIVE]);
        $this->makeEntry(['reference' => 'AD-CMP', 'status' => AccrualDeferral::STATUS_COMPLETED]);

        $response = $this->withToken($this->token)
            ->getJson("{$this->baseUrl}?status=active");

        $response->assertStatus(200);
        foreach ($response->json('data') as $item) {
            $this->assertSame('active', $item['status']);
        }
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_can_create_an_accrual_entry(): void
    {
        $response = $this->withToken($this->token)
            ->postJson($this->baseUrl, $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.reference', 'AD-0001')
            ->assertJsonPath('data.type', 'accrual')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.periods_total', 12)
            ->assertJsonPath('data.periods_posted', 0);

        $this->assertDatabaseHas('accrual_deferrals', [
            'reference'       => 'AD-0001',
            'organization_id' => $this->organization->id,
            'periods_total'   => 12,
        ]);
    }

    public function test_create_computes_per_period_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson($this->baseUrl, $this->validPayload([
                'total_amount'  => 6000.00,
                'periods_total' => 3,
            ]));

        $response->assertStatus(201);

        $entry = AccrualDeferral::withoutGlobalScopes()->where('reference', 'AD-0001')->sole();
        $this->assertSame('2000.0000', (string) $entry->per_period_amount);
    }

    public function test_create_requires_reference_type_accounts_dates_and_periods(): void
    {
        $this->withToken($this->token)
            ->postJson($this->baseUrl, [])
            ->assertStatus(422);
    }

    public function test_create_rejects_invalid_type(): void
    {
        $this->withToken($this->token)
            ->postJson($this->baseUrl, $this->validPayload(['type' => 'amortisation']))
            ->assertStatus(422);
    }

    public function test_create_rejects_zero_amount(): void
    {
        $this->withToken($this->token)
            ->postJson($this->baseUrl, $this->validPayload(['total_amount' => 0]))
            ->assertStatus(422);
    }

    public function test_create_rejects_end_date_not_after_start_date(): void
    {
        $this->withToken($this->token)
            ->postJson($this->baseUrl, $this->validPayload([
                'start_date' => '2026-06-01',
                'end_date'   => '2026-06-01', // same, not after
            ]))
            ->assertStatus(422);
    }

    public function test_create_rejects_zero_periods(): void
    {
        $this->withToken($this->token)
            ->postJson($this->baseUrl, $this->validPayload(['periods_total' => 0]))
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_can_show_entry_with_relations(): void
    {
        $entry = $this->makeEntry();

        $response = $this->withToken($this->token)
            ->getJson("{$this->baseUrl}/{$entry->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.uuid', $entry->uuid)
            ->assertJsonPath('data.reference', $entry->reference);

        $this->assertArrayHasKey('debit_account', $response->json('data'));
        $this->assertArrayHasKey('credit_account', $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_can_update_description_on_active_entry(): void
    {
        $entry = $this->makeEntry();

        $this->withToken($this->token)
            ->putJson("{$this->baseUrl}/{$entry->uuid}", [
                'description' => 'Updated description',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.description', 'Updated description');

        $this->assertDatabaseHas('accrual_deferrals', [
            'id'          => $entry->id,
            'description' => 'Updated description',
        ]);
    }

    public function test_cannot_update_a_completed_entry(): void
    {
        $entry = $this->makeEntry(['status' => AccrualDeferral::STATUS_COMPLETED]);

        $this->withToken($this->token)
            ->putJson("{$this->baseUrl}/{$entry->uuid}", ['description' => 'Too late'])
            ->assertStatus(422);
    }

    public function test_cannot_update_a_cancelled_entry(): void
    {
        $entry = $this->makeEntry(['status' => AccrualDeferral::STATUS_CANCELLED]);

        $this->withToken($this->token)
            ->putJson("{$this->baseUrl}/{$entry->uuid}", ['description' => 'No'])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_can_cancel_and_soft_delete_an_active_entry(): void
    {
        $entry = $this->makeEntry();

        $this->withToken($this->token)
            ->deleteJson("{$this->baseUrl}/{$entry->uuid}")
            ->assertStatus(200);

        $this->assertSoftDeleted('accrual_deferrals', ['id' => $entry->id]);
        $this->assertDatabaseHas('accrual_deferrals', [
            'id'     => $entry->id,
            'status' => AccrualDeferral::STATUS_CANCELLED,
        ]);
    }

    public function test_cannot_delete_a_completed_entry(): void
    {
        $entry = $this->makeEntry([
            'status'         => AccrualDeferral::STATUS_COMPLETED,
            'periods_posted' => 12,
        ]);

        $this->withToken($this->token)
            ->deleteJson("{$this->baseUrl}/{$entry->uuid}")
            ->assertStatus(422);

        $this->assertDatabaseHas('accrual_deferrals', ['id' => $entry->id]);
    }

    // -------------------------------------------------------------------------
    // Post Period
    // -------------------------------------------------------------------------

    public function test_post_period_creates_journal_entry_and_increments_periods_posted(): void
    {
        $entry = $this->makeEntry(['periods_total' => 3, 'per_period_amount' => 4000.00]);

        $response = $this->withToken($this->token)
            ->postJson("{$this->baseUrl}/{$entry->uuid}/post-period", ['period' => 1]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $entry->refresh();
        $this->assertSame(1, $entry->periods_posted);
        $this->assertSame(AccrualDeferral::STATUS_ACTIVE, $entry->status);
    }

    public function test_post_final_period_marks_entry_completed(): void
    {
        $entry = $this->makeEntry([
            'periods_total'  => 2,
            'periods_posted' => 1,
            'per_period_amount' => 6000.00,
        ]);

        $this->withToken($this->token)
            ->postJson("{$this->baseUrl}/{$entry->uuid}/post-period", ['period' => 2])
            ->assertStatus(200);

        $entry->refresh();
        $this->assertSame(2, $entry->periods_posted);
        $this->assertSame(AccrualDeferral::STATUS_COMPLETED, $entry->status);
    }

    public function test_post_period_rejects_already_posted_period(): void
    {
        $entry = $this->makeEntry(['periods_posted' => 2, 'periods_total' => 3]);

        $this->withToken($this->token)
            ->postJson("{$this->baseUrl}/{$entry->uuid}/post-period", ['period' => 1])
            ->assertStatus(422); // period 1 ≤ periods_posted (2)
    }

    public function test_post_period_rejects_out_of_range_period(): void
    {
        $entry = $this->makeEntry(['periods_total' => 3]);

        $this->withToken($this->token)
            ->postJson("{$this->baseUrl}/{$entry->uuid}/post-period", ['period' => 99])
            ->assertStatus(422);
    }

    public function test_post_period_rejects_inactive_entry(): void
    {
        $entry = $this->makeEntry(['status' => AccrualDeferral::STATUS_CANCELLED]);

        $this->withToken($this->token)
            ->postJson("{$this->baseUrl}/{$entry->uuid}/post-period", ['period' => 1])
            ->assertStatus(422);
    }

    public function test_post_period_creates_a_posted_journal_entry_in_db(): void
    {
        $entry = $this->makeEntry(['per_period_amount' => 1000.00]);

        $this->withToken($this->token)
            ->postJson("{$this->baseUrl}/{$entry->uuid}/post-period", ['period' => 1])
            ->assertStatus(200);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $this->organization->id,
            'status'          => 'posted',
            'reference'       => $entry->reference . '-P1',
        ]);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson($this->baseUrl)->assertStatus(401);
    }
}
