<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ArInterestRunTest extends TestCase
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
    // Helpers
    // -------------------------------------------------------------------------

    private function makeOverdueInvoice(float $amountDue, int $daysOverdue = 30): Invoice
    {
        $contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'company_name'    => 'Test Customer',
        ]);

        return Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id'     => $contact->id,
            'status'          => Invoice::STATUS_OVERDUE,
            'due_date'        => now()->subDays($daysOverdue)->toDateString(),
            'total'           => $amountDue,
            'amount_paid'     => 0,
            'amount_due'      => $amountDue,
        ]);
    }

    private function makeArAccount(): Account
    {
        return Account::factory()->create([
            'organization_id' => $this->organization->id,
            'sub_type'        => Account::SUBTYPE_RECEIVABLE,
            'is_active'       => true,
        ]);
    }

    private function makeIncomeAccount(): Account
    {
        return Account::factory()->create([
            'organization_id' => $this->organization->id,
            'sub_type'        => Account::SUBTYPE_OTHER_INCOME,
            'is_active'       => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Preview
    // -------------------------------------------------------------------------

    public function test_preview_returns_expected_structure(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/ar-interest-runs/preview');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['lines', 'total_interest', 'invoice_count'],
            ]);
    }

    public function test_preview_returns_empty_when_no_overdue_invoices(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/ar-interest-runs/preview');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.lines'));
        $this->assertSame(0, $response->json('data.invoice_count'));
        $this->assertEquals(0.0, $response->json('data.total_interest'));
    }

    public function test_preview_calculates_interest_correctly(): void
    {
        // 1000 SAR overdue for 30 days at 12% p.a.
        // interest = 1000 × (12/100/365) × 30 = 9.863...
        $this->makeOverdueInvoice(1000.0, 30);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/ar-interest-runs/preview?annual_rate=12');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.invoice_count'));

        $interest = $response->json('data.total_interest');
        $expected = round(1000.0 * (12.0 / 100.0 / 365.0) * 30, 2);
        $this->assertEqualsWithDelta($expected, $interest, 0.01);
    }

    public function test_preview_excludes_non_overdue_invoices(): void
    {
        $contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'company_name'    => 'Non-Overdue Corp',
        ]);
        // Draft invoice — should not appear
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id'     => $contact->id,
            'status'          => Invoice::STATUS_DRAFT,
            'due_date'        => now()->subDays(10)->toDateString(),
            'amount_due'      => 500.0,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/ar-interest-runs/preview');

        $this->assertEmpty($response->json('data.lines'));
    }

    public function test_preview_filters_by_min_days_overdue(): void
    {
        $this->makeOverdueInvoice(1000.0, 5);   // 5 days — should be excluded
        $this->makeOverdueInvoice(2000.0, 45);  // 45 days — should be included

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/ar-interest-runs/preview?min_days_overdue=30');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.invoice_count'));
    }

    public function test_preview_rejects_invalid_annual_rate(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/ar-interest-runs/preview?annual_rate=0');

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Execute
    // -------------------------------------------------------------------------

    public function test_execute_returns_zero_entries_when_no_overdue_invoices(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/ar-interest-runs/execute');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.journal_entries_posted'));
    }

    public function test_execute_warns_when_accounts_not_configured(): void
    {
        $this->makeOverdueInvoice(1000.0, 30);
        // No AR or income accounts seeded

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/ar-interest-runs/execute', ['annual_rate' => 12]);

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.journal_entries_posted'));
        $this->assertNotNull($response->json('data.warning'));
    }

    public function test_execute_posts_journal_entries(): void
    {
        $this->makeOverdueInvoice(1000.0, 30);
        $this->makeArAccount();
        $this->makeIncomeAccount();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/ar-interest-runs/execute', ['annual_rate' => 12]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.journal_entries_posted'));
        $this->assertGreaterThan(0, $response->json('data.total_interest'));
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/ar-interest-runs/preview')->assertStatus(401);
        $this->postJson('/api/v1/ar-interest-runs/execute')->assertStatus(401);
    }
}
