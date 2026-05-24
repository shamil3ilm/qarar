<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Models\Compliance\QatarGtaSubmission;
use App\Services\Compliance\QatarGtaEInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class QatarGtaEInvoiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private QatarGtaEInvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('QA'); // Qatar org
        $this->setUpAuthenticatedUser();
        $this->service = app(QatarGtaEInvoiceService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // XML builder
    // ─────────────────────────────────────────────────────────────────────────

    public function test_xml_output_is_valid(): void
    {
        $xml = $this->service->buildXml($this->sampleData());

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml), 'Qatar GTA XML is not valid');
    }

    public function test_xml_contains_ubl_21_version(): void
    {
        $xml = $this->service->buildXml($this->sampleData());
        $this->assertStringContainsString('<cbc:UBLVersionID>2.1</cbc:UBLVersionID>', $xml);
    }

    public function test_xml_contains_gta_customization_id(): void
    {
        $xml = $this->service->buildXml($this->sampleData());
        $this->assertStringContainsString('urn:gta.gov.qa:einvoice:1.0', $xml);
    }

    public function test_xml_standard_invoice_type_code_is_388(): void
    {
        $xml = $this->service->buildXml($this->sampleData(['invoice_type' => 'invoice']));
        $this->assertStringContainsString('>388<', $xml);
    }

    public function test_xml_credit_note_type_code_is_381(): void
    {
        $xml = $this->service->buildXml($this->sampleData(['invoice_type' => 'credit_note']));
        $this->assertStringContainsString('>381<', $xml);
    }

    public function test_xml_currency_is_qar(): void
    {
        $xml = $this->service->buildXml($this->sampleData());
        $this->assertStringContainsString('<cbc:DocumentCurrencyCode>QAR</cbc:DocumentCurrencyCode>', $xml);
    }

    public function test_xml_includes_seller_trn(): void
    {
        $data = $this->sampleData(['seller_trn' => '12345678901']);
        $xml  = $this->service->buildXml($data);
        $this->assertStringContainsString('12345678901', $xml);
    }

    public function test_xml_includes_billing_reference_for_credit_note(): void
    {
        $data = $this->sampleData([
            'invoice_type'      => 'credit_note',
            'billing_reference' => 'QA-INV-2025-001',
        ]);
        $xml = $this->service->buildXml($data);
        $this->assertStringContainsString('QA-INV-2025-001', $xml);
        $this->assertStringContainsString('BillingReference', $xml);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // QR code
    // ─────────────────────────────────────────────────────────────────────────

    public function test_qr_code_is_valid_base64(): void
    {
        $qr = $this->service->buildQrCode($this->sampleData());
        $this->assertNotFalse(base64_decode($qr, strict: true), 'QR code is not valid base64');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // prepare()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_prepare_creates_pending_submission(): void
    {
        $submission = $this->service->prepare(
            array_merge($this->sampleData(), ['organization_id' => $this->organization->id]),
            $this->user->id,
        );

        $this->assertEquals(QatarGtaSubmission::STATUS_PENDING, $submission->status);
        $this->assertDatabaseHas('qatar_gta_submissions', [
            'organization_id' => $this->organization->id,
            'status'          => 'pending',
        ]);
    }

    public function test_prepare_stores_xml(): void
    {
        $submission = $this->service->prepare(
            array_merge($this->sampleData(), ['organization_id' => $this->organization->id]),
            $this->user->id,
        );

        $this->assertNotEmpty($submission->invoice_xml);
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($submission->invoice_xml));
    }

    public function test_prepare_throws_when_required_field_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->sampleData();
        unset($data['invoice_number']);
        $this->service->prepare(
            array_merge($data, ['organization_id' => $this->organization->id]),
            $this->user->id,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Status transitions
    // ─────────────────────────────────────────────────────────────────────────

    public function test_mark_submitted_transitions_status(): void
    {
        $submission = QatarGtaSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => QatarGtaSubmission::STATUS_PENDING,
        ]);

        $updated = $this->service->markSubmitted($submission, 'GTA-QA-2025-001');

        $this->assertEquals(QatarGtaSubmission::STATUS_SUBMITTED, $updated->status);
        $this->assertEquals('GTA-QA-2025-001', $updated->gta_submission_id);
        $this->assertNotNull($updated->submitted_at);
    }

    public function test_mark_submitted_throws_if_not_pending(): void
    {
        $submission = QatarGtaSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => QatarGtaSubmission::STATUS_SUBMITTED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->markSubmitted($submission);
    }

    public function test_mark_accepted_sets_status(): void
    {
        $submission = QatarGtaSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => QatarGtaSubmission::STATUS_SUBMITTED,
        ]);

        $updated = $this->service->markAccepted($submission, 'OK');

        $this->assertEquals(QatarGtaSubmission::STATUS_ACCEPTED, $updated->status);
        $this->assertNotNull($updated->acknowledged_at);
    }

    public function test_mark_rejected_sets_status(): void
    {
        $submission = QatarGtaSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => QatarGtaSubmission::STATUS_SUBMITTED,
        ]);

        $updated = $this->service->markRejected($submission, 'Invalid TRN format');

        $this->assertEquals(QatarGtaSubmission::STATUS_REJECTED, $updated->status);
        $this->assertEquals('Invalid TRN format', $updated->gta_response);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function sampleData(array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $this->organization->id,
            'invoice_number'  => 'QA-INV-2025-001',
            'invoice_type'    => 'invoice',
            'issue_date'      => '2025-06-15',
            'currency_code'   => 'QAR',
            'seller_name'     => 'Qatar Trading Co.',
            'seller_trn'      => '12345678901',
            'buyer_name'      => 'Ministry of Finance',
            'buyer_trn'       => '98765432100',
            'subtotal'        => 25_000.0,
            'tax_amount'      => 0.0,
            'total_amount'    => 25_000.0,
        ], $overrides);
    }
}
