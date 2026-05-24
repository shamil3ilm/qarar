<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Models\Compliance\FtaEInvoiceSubmission;
use App\Services\Compliance\FtaEInvoiceService;
use App\Services\Compliance\FtaUblBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class FtaEInvoiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private FtaEInvoiceService $service;
    private FtaUblBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('AE'); // UAE org
        $this->setUpAuthenticatedUser();
        $this->service = app(FtaEInvoiceService::class);
        $this->builder = app(FtaUblBuilder::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UBL builder — XML structure
    // ─────────────────────────────────────────────────────────────────────────

    public function test_ubl_output_is_valid_xml(): void
    {
        $xml = $this->builder->buildInvoice($this->sampleData());

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml), 'FTA UBL output is not valid XML');
    }

    public function test_ubl_contains_ubl_21_version(): void
    {
        $xml = $this->builder->buildInvoice($this->sampleData());
        $this->assertStringContainsString('<cbc:UBLVersionID>2.1</cbc:UBLVersionID>', $xml);
    }

    public function test_ubl_contains_fta_customization_id(): void
    {
        $xml = $this->builder->buildInvoice($this->sampleData());
        $this->assertStringContainsString('urn:fta.gov.ae:einvoice:1.0', $xml);
    }

    public function test_ubl_standard_invoice_type_code_is_388(): void
    {
        $xml = $this->builder->buildInvoice($this->sampleData(['invoice_type' => 'invoice']));
        $this->assertStringContainsString('>388<', $xml);
    }

    public function test_ubl_credit_note_type_code_is_381(): void
    {
        $xml = $this->builder->buildInvoice($this->sampleData(['invoice_type' => 'credit_note']));
        $this->assertStringContainsString('>381<', $xml);
    }

    public function test_ubl_currency_is_aed(): void
    {
        $xml = $this->builder->buildInvoice($this->sampleData());
        $this->assertStringContainsString('<cbc:DocumentCurrencyCode>AED</cbc:DocumentCurrencyCode>', $xml);
    }

    public function test_ubl_includes_seller_trn(): void
    {
        $data = $this->sampleData(['seller_trn' => '100345678900003']);
        $xml  = $this->builder->buildInvoice($data);
        $this->assertStringContainsString('100345678900003', $xml);
    }

    public function test_ubl_includes_buyer_trn(): void
    {
        $data = $this->sampleData(['buyer_trn' => '200987654300001']);
        $xml  = $this->builder->buildInvoice($data);
        $this->assertStringContainsString('200987654300001', $xml);
    }

    public function test_ubl_includes_tax_amount(): void
    {
        $data = $this->sampleData(['tax_amount' => 500.0]);
        $xml  = $this->builder->buildInvoice($data);
        $this->assertStringContainsString('500.00', $xml);
    }

    public function test_ubl_contains_billing_reference_for_credit_note(): void
    {
        $data = $this->sampleData([
            'invoice_type'      => 'credit_note',
            'billing_reference' => 'INV-2025-001',
        ]);
        $xml = $this->builder->buildInvoice($data);
        $this->assertStringContainsString('INV-2025-001', $xml);
        $this->assertStringContainsString('BillingReference', $xml);
    }

    public function test_ubl_includes_invoice_lines(): void
    {
        $data = $this->sampleData([
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 500, 'line_total' => 1000],
            ],
        ]);
        $xml = $this->builder->buildInvoice($data);
        $this->assertStringContainsString('<cac:InvoiceLine>', $xml);
        $this->assertStringContainsString('Consulting', $xml);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // QR code
    // ─────────────────────────────────────────────────────────────────────────

    public function test_qr_code_is_base64_encoded(): void
    {
        $qr = $this->builder->buildQrCode($this->sampleData());
        $this->assertNotEmpty($qr);
        $decoded = base64_decode($qr, strict: true);
        $this->assertNotFalse($decoded, 'QR code is not valid base64');
    }

    public function test_qr_code_contains_seller_name_in_tlv(): void
    {
        $data = $this->sampleData(['seller_name' => 'Acme UAE LLC']);
        $qr   = $this->builder->buildQrCode($data);
        $raw  = base64_decode($qr);
        $this->assertStringContainsString('Acme UAE LLC', $raw);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service — prepare()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_prepare_creates_pending_submission(): void
    {
        $submission = $this->service->prepare(
            array_merge($this->sampleData(), ['organization_id' => $this->organization->id]),
            $this->user->id,
        );

        $this->assertEquals(FtaEInvoiceSubmission::STATUS_PENDING, $submission->status);
        $this->assertDatabaseHas('fta_einvoice_submissions', [
            'organization_id' => $this->organization->id,
            'invoice_number'  => $submission->invoice_number,
            'status'          => 'pending',
        ]);
    }

    public function test_prepare_stores_ubl_xml(): void
    {
        $submission = $this->service->prepare(
            array_merge($this->sampleData(), ['organization_id' => $this->organization->id]),
            $this->user->id,
        );

        $this->assertNotEmpty($submission->ubl_xml);
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($submission->ubl_xml));
    }

    public function test_prepare_stores_qr_code(): void
    {
        $submission = $this->service->prepare(
            array_merge($this->sampleData(), ['organization_id' => $this->organization->id]),
            $this->user->id,
        );

        $this->assertNotEmpty($submission->qr_code_data);
    }

    public function test_prepare_throws_when_required_field_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->sampleData();
        unset($data['invoice_number']);
        $this->service->prepare(array_merge($data, ['organization_id' => $this->organization->id]), $this->user->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service — status transitions
    // ─────────────────────────────────────────────────────────────────────────

    public function test_mark_submitted_transitions_status(): void
    {
        $submission = FtaEInvoiceSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => FtaEInvoiceSubmission::STATUS_PENDING,
        ]);

        $updated = $this->service->markSubmitted($submission, 'FTA-2025-999');

        $this->assertEquals(FtaEInvoiceSubmission::STATUS_SUBMITTED, $updated->status);
        $this->assertEquals('FTA-2025-999', $updated->fta_submission_id);
        $this->assertNotNull($updated->submitted_at);
    }

    public function test_mark_submitted_throws_if_not_pending(): void
    {
        $submission = FtaEInvoiceSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => FtaEInvoiceSubmission::STATUS_SUBMITTED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->markSubmitted($submission);
    }

    public function test_mark_accepted_sets_status_and_timestamp(): void
    {
        $submission = FtaEInvoiceSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => FtaEInvoiceSubmission::STATUS_SUBMITTED,
        ]);

        $updated = $this->service->markAccepted($submission, 'OK');

        $this->assertEquals(FtaEInvoiceSubmission::STATUS_ACCEPTED, $updated->status);
        $this->assertNotNull($updated->acknowledged_at);
    }

    public function test_mark_rejected_sets_status(): void
    {
        $submission = FtaEInvoiceSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => FtaEInvoiceSubmission::STATUS_SUBMITTED,
        ]);

        $updated = $this->service->markRejected($submission, 'Invalid TRN');

        $this->assertEquals(FtaEInvoiceSubmission::STATUS_REJECTED, $updated->status);
        $this->assertEquals('Invalid TRN', $updated->fta_response);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function sampleData(array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $this->organization->id,
            'invoice_number'  => 'INV-AE-2025-001',
            'invoice_type'    => 'invoice',
            'issue_date'      => '2025-03-15',
            'currency_code'   => 'AED',
            'seller_name'     => 'Acme UAE LLC',
            'seller_trn'      => '100345678900003',
            'buyer_name'      => 'Buyer Corp',
            'buyer_trn'       => '200987654300001',
            'subtotal'        => 10000.0,
            'tax_amount'      => 500.0,
            'total_amount'    => 10500.0,
            'tax_rate'        => 5.0,
        ], $overrides);
    }
}
