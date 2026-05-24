<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Purchase\Bill;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AgingReportTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    // Fixed reference date for all date-sensitive assertions
    private const AS_OF = '2026-01-31';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeContact(string $companyName): Contact
    {
        return Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'company_name'    => $companyName,
            'contact_name'    => $companyName . ' Rep',
        ]);
    }

    private function makeInvoice(Contact $customer, string $dueDate, float $total, float $paid = 0): Invoice
    {
        return Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id'     => $customer->id,
            'status'          => Invoice::STATUS_OVERDUE,
            'due_date'        => $dueDate,
            'total'           => $total,
            'amount_paid'     => $paid,
            'amount_due'      => $total - $paid,
        ]);
    }

    private function makeBill(Contact $supplier, string $dueDate, float $total, float $paid = 0): Bill
    {
        // Use STATUS_APPROVED: SQLite ENUM CHECK doesn't include 'overdue' (MySQL-only migration)
        return Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'supplier_id'     => $supplier->id,
            'status'          => Bill::STATUS_APPROVED,
            'due_date'        => $dueDate,
            'total'           => $total,
            'amount_paid'     => $paid,
            'amount_due'      => $total - $paid,
        ]);
    }

    // -------------------------------------------------------------------------
    // AR Aging — structure
    // -------------------------------------------------------------------------

    public function test_ar_aging_returns_expected_structure(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/ar-aging?as_of_date=' . self::AS_OF);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'as_of_date',
                    'by_contact',
                    'totals'   => ['current', '1_30', '31_60', '61_90', '91_120', 'over_120'],
                    'grand_total',
                    'bucket_labels',
                ],
            ]);

        $this->assertSame(self::AS_OF, $response->json('data.as_of_date'));
    }

    public function test_ar_aging_returns_empty_when_no_outstanding_invoices(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/ar-aging?as_of_date=' . self::AS_OF);

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.by_contact'));
        $this->assertSame('0.0000', $response->json('data.grand_total'));
    }

    // -------------------------------------------------------------------------
    // AR Aging — bucketing
    // -------------------------------------------------------------------------

    public function test_ar_aging_places_not_yet_due_invoice_in_current_bucket(): void
    {
        $customer = $this->makeContact('Future Corp');
        $this->makeInvoice($customer, '2026-02-15', 1000.00); // due after AS_OF

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/ar-aging?as_of_date=' . self::AS_OF);

        $response->assertStatus(200);
        $row = collect($response->json('data.by_contact'))->firstWhere('contact_id', $customer->id);

        $this->assertNotNull($row);
        $this->assertSame('1000.0000', $row['current']);
        $this->assertSame('0.0000', $row['1_30']);
    }

    public function test_ar_aging_places_invoice_in_correct_overdue_buckets(): void
    {
        $customer = $this->makeContact('Overdue Corp');

        // 1–30 days overdue: due 2026-01-15 (16 days before AS_OF)
        $this->makeInvoice($customer, '2026-01-15', 500.00);
        // 31–60 days overdue: due 2025-12-10 (52 days before AS_OF)
        $this->makeInvoice($customer, '2025-12-10', 600.00);
        // Over 120 days overdue: due 2025-08-01 (183 days before AS_OF)
        $this->makeInvoice($customer, '2025-08-01', 700.00);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/ar-aging?as_of_date=' . self::AS_OF);

        $response->assertStatus(200);
        $row = collect($response->json('data.by_contact'))->firstWhere('contact_id', $customer->id);

        $this->assertSame('500.0000', $row['1_30']);
        $this->assertSame('600.0000', $row['31_60']);
        $this->assertSame('700.0000', $row['over_120']);
        $this->assertSame('1800.0000', $row['total']);
    }

    public function test_ar_aging_totals_sum_all_contacts(): void
    {
        $c1 = $this->makeContact('Alpha Ltd');
        $c2 = $this->makeContact('Beta Ltd');

        $this->makeInvoice($c1, '2026-01-15', 1000.00); // 1_30
        $this->makeInvoice($c2, '2026-01-20', 2000.00); // 1_30

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/ar-aging?as_of_date=' . self::AS_OF);

        $data = $response->json('data');
        $this->assertSame('3000.0000', $data['totals']['1_30']);
        $this->assertSame('3000.0000', $data['grand_total']);
    }

    public function test_ar_aging_excludes_fully_paid_invoices(): void
    {
        $customer = $this->makeContact('Paid Corp');
        // total == amount_paid → whereColumn('total', '>', 'amount_paid') excludes it
        $this->makeInvoice($customer, '2026-01-15', 1000.00, 1000.00);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/ar-aging?as_of_date=' . self::AS_OF);

        $this->assertEmpty($response->json('data.by_contact'));
    }

    public function test_ar_aging_excludes_draft_invoices(): void
    {
        $customer = $this->makeContact('Draft Corp');
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id'     => $customer->id,
            'status'          => Invoice::STATUS_DRAFT,
            'due_date'        => '2026-01-15',
            'total'           => 1000.00,
            'amount_paid'     => 0,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/ar-aging?as_of_date=' . self::AS_OF);

        $this->assertEmpty($response->json('data.by_contact'));
    }

    public function test_ar_aging_only_returns_own_organization_invoices(): void
    {
        $otherOrg = \App\Models\Core\Organization::factory()->create();
        $otherContact = Contact::factory()->create(['organization_id' => $otherOrg->id]);
        Invoice::factory()->create([
            'organization_id' => $otherOrg->id,
            'customer_id'     => $otherContact->id,
            'status'          => Invoice::STATUS_OVERDUE,
            'due_date'        => '2026-01-15',
            'total'           => 9999.00,
            'amount_paid'     => 0,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/ar-aging?as_of_date=' . self::AS_OF);

        $this->assertEmpty($response->json('data.by_contact'));
    }

    public function test_ar_aging_defaults_as_of_date_to_today(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/ar-aging');

        $response->assertStatus(200);
        $this->assertSame(now()->toDateString(), $response->json('data.as_of_date'));
    }

    // -------------------------------------------------------------------------
    // AP Aging — structure and bucketing
    // -------------------------------------------------------------------------

    public function test_ap_aging_returns_expected_structure(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/ap-aging?as_of_date=' . self::AS_OF);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'as_of_date',
                    'by_contact',
                    'totals'   => ['current', '1_30', '31_60', '61_90', '91_120', 'over_120'],
                    'grand_total',
                    'bucket_labels',
                ],
            ]);
    }

    public function test_ap_aging_buckets_bills_correctly(): void
    {
        $supplier = $this->makeContact('Supply House');

        $this->makeBill($supplier, '2026-01-15', 800.00);  // 1_30 (16 days)
        $this->makeBill($supplier, '2025-11-01', 1200.00); // 91_120 (91 days)

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/ap-aging?as_of_date=' . self::AS_OF);

        $response->assertStatus(200);
        $row = collect($response->json('data.by_contact'))->firstWhere('contact_id', $supplier->id);

        $this->assertNotNull($row);
        $this->assertSame('800.0000', $row['1_30']);
        $this->assertSame('1200.0000', $row['91_120']);
        $this->assertSame('2000.0000', $row['total']);
    }

    public function test_ap_aging_excludes_draft_bills(): void
    {
        $supplier = $this->makeContact('Draft Supplier');
        Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'supplier_id'     => $supplier->id,
            'status'          => Bill::STATUS_DRAFT,
            'due_date'        => '2026-01-15',
            'total'           => 500.00,
            'amount_paid'     => 0,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/ap-aging?as_of_date=' . self::AS_OF);

        $this->assertEmpty($response->json('data.by_contact'));
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/reports/ar-aging')->assertStatus(401);
        $this->getJson('/api/v1/reports/ap-aging')->assertStatus(401);
    }
}
