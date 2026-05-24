<?php

declare(strict_types=1);

namespace Tests\Feature\Tax;

use App\Models\Tax\IndiaEInvoiceSubmission;
use App\Services\Tax\IndiaIrnBuilder;
use App\Services\Tax\IndiaIrpEInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class IndiaEInvoiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private IndiaIrpEInvoiceService $service;
    private IndiaIrnBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('IN');
        $this->setUpAuthenticatedUser();
        $this->service = app(IndiaIrpEInvoiceService::class);
        $this->builder = app(IndiaIrnBuilder::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // IRN computation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_irn_is_64_character_hex_string(): void
    {
        $irn = $this->builder->computeIrn('27AAPFU0939F1ZV', 'INV', 'INV-001', '01/04/2025');

        $this->assertEquals(64, strlen($irn));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $irn);
    }

    public function test_irn_is_sha256_of_gstin_doctype_docno_docdate(): void
    {
        $gstin   = '27AAPFU0939F1ZV';
        $docType = 'INV';
        $docNo   = 'INV-001';
        $docDate = '01/04/2025';

        $expected = hash('sha256', "{$gstin}/{$docType}/{$docNo}/{$docDate}");
        $actual   = $this->builder->computeIrn($gstin, $docType, $docNo, $docDate);

        $this->assertEquals($expected, $actual);
    }

    public function test_irn_differs_for_different_documents(): void
    {
        $irn1 = $this->builder->computeIrn('27AAPFU0939F1ZV', 'INV', 'INV-001', '01/04/2025');
        $irn2 = $this->builder->computeIrn('27AAPFU0939F1ZV', 'INV', 'INV-002', '01/04/2025');

        $this->assertNotEquals($irn1, $irn2);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Payload builder
    // ─────────────────────────────────────────────────────────────────────────

    public function test_payload_contains_version_1_1(): void
    {
        $payload = $this->builder->buildPayload($this->sampleData());
        $this->assertEquals('1.1', $payload['Version']);
    }

    public function test_payload_contains_seller_gstin(): void
    {
        $payload = $this->builder->buildPayload($this->sampleData());
        $this->assertEquals('27AAPFU0939F1ZV', $payload['SellerDtls']['Gstin']);
    }

    public function test_payload_contains_buyer_gstin(): void
    {
        $payload = $this->builder->buildPayload($this->sampleData());
        $this->assertEquals('07AACCS7531C1ZJ', $payload['BuyerDtls']['Gstin']);
    }

    public function test_payload_contains_value_details(): void
    {
        $data    = $this->sampleData(['taxable_value' => 100_000.0, 'cgst_amount' => 9_000.0, 'sgst_amount' => 9_000.0, 'total_amount' => 118_000.0]);
        $payload = $this->builder->buildPayload($data);

        $this->assertEquals(100_000.0, $payload['ValDtls']['AssVal']);
        $this->assertEquals(9_000.0, $payload['ValDtls']['CgstVal']);
        $this->assertEquals(9_000.0, $payload['ValDtls']['SgstVal']);
        $this->assertEquals(118_000.0, $payload['ValDtls']['TotInvVal']);
    }

    public function test_payload_includes_item_list(): void
    {
        $data = $this->sampleData([
            'items' => [
                ['description' => 'Laptop', 'quantity' => 2, 'unit_price' => 50_000, 'total_amount' => 100_000, 'gst_rate' => 18, 'cgst_amount' => 9_000, 'sgst_amount' => 9_000, 'total_with_tax' => 118_000],
            ],
        ]);
        $payload = $this->builder->buildPayload($data);

        $this->assertCount(1, $payload['ItemList']);
        $this->assertEquals('Laptop', $payload['ItemList'][0]['PrdDesc']);
        $this->assertEquals(2.0, $payload['ItemList'][0]['Qty']);
    }

    public function test_payload_irn_matches_computed_hash(): void
    {
        $data    = $this->sampleData();
        $payload = $this->builder->buildPayload($data);
        $expected = $this->builder->computeIrn(
            $data['gstin_seller'],
            $data['document_type'],
            $data['document_number'],
            $data['document_date'],
        );

        $this->assertEquals($expected, $payload['_irn']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // QR code
    // ─────────────────────────────────────────────────────────────────────────

    public function test_qr_data_is_base64_encoded_json(): void
    {
        $data = $this->sampleData();
        $irn  = $this->builder->computeIrn($data['gstin_seller'], $data['document_type'], $data['document_number'], $data['document_date']);
        $qr   = $this->builder->buildQrData($data, $irn);

        $decoded = base64_decode($qr, strict: true);
        $this->assertNotFalse($decoded);

        $json = json_decode($decoded, true);
        $this->assertEquals($data['gstin_seller'], $json['SellerGstin']);
        $this->assertEquals($irn, $json['Irn']);
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

        $this->assertEquals(IndiaEInvoiceSubmission::STATUS_PENDING, $submission->status);
        $this->assertDatabaseHas('india_einvoice_submissions', [
            'organization_id' => $this->organization->id,
            'document_number' => $submission->document_number,
            'status'          => 'pending',
        ]);
    }

    public function test_prepare_stores_irn(): void
    {
        $submission = $this->service->prepare(
            array_merge($this->sampleData(), ['organization_id' => $this->organization->id]),
            $this->user->id,
        );

        $this->assertNotEmpty($submission->irn);
        $this->assertEquals(64, strlen($submission->irn));
    }

    public function test_prepare_stores_einvoice_json(): void
    {
        $submission = $this->service->prepare(
            array_merge($this->sampleData(), ['organization_id' => $this->organization->id]),
            $this->user->id,
        );

        $this->assertNotEmpty($submission->einvoice_json);
        $decoded = json_decode($submission->einvoice_json, true);
        $this->assertEquals('1.1', $decoded['Version']);
    }

    public function test_prepare_throws_when_required_field_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = $this->sampleData();
        unset($data['gstin_seller']);
        $this->service->prepare(array_merge($data, ['organization_id' => $this->organization->id]), $this->user->id);
    }

    public function test_prepare_throws_for_invalid_gstin_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('gstin_seller must be exactly 15 characters');

        $data = $this->sampleData(['gstin_seller' => 'TOOSHORT']);
        $this->service->prepare(array_merge($data, ['organization_id' => $this->organization->id]), $this->user->id);
    }

    public function test_prepare_normalizes_ddmmyyyy_date(): void
    {
        $data = $this->sampleData(['document_date' => '01/04/2025']);
        $submission = $this->service->prepare(
            array_merge($data, ['organization_id' => $this->organization->id]),
            $this->user->id,
        );

        $this->assertEquals('2025-04-01', $submission->document_date);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Status transitions
    // ─────────────────────────────────────────────────────────────────────────

    public function test_mark_submitted_transitions_status(): void
    {
        $submission = IndiaEInvoiceSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => IndiaEInvoiceSubmission::STATUS_PENDING,
        ]);

        $updated = $this->service->markSubmitted($submission);
        $this->assertEquals(IndiaEInvoiceSubmission::STATUS_SUBMITTED, $updated->status);
    }

    public function test_mark_submitted_throws_if_not_pending(): void
    {
        $submission = IndiaEInvoiceSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => IndiaEInvoiceSubmission::STATUS_SUBMITTED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->markSubmitted($submission);
    }

    public function test_mark_accepted_stores_ack_number(): void
    {
        $submission = IndiaEInvoiceSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => IndiaEInvoiceSubmission::STATUS_SUBMITTED,
        ]);

        $updated = $this->service->markAccepted($submission, '112502250678912');
        $this->assertEquals(IndiaEInvoiceSubmission::STATUS_ACCEPTED, $updated->status);
        $this->assertEquals('112502250678912', $updated->irp_ack_number);
        $this->assertNotNull($updated->irp_ack_date);
    }

    public function test_mark_rejected_sets_status_and_reason(): void
    {
        $submission = IndiaEInvoiceSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => IndiaEInvoiceSubmission::STATUS_SUBMITTED,
        ]);

        $updated = $this->service->markRejected($submission, 'Duplicate IRN');
        $this->assertEquals(IndiaEInvoiceSubmission::STATUS_REJECTED, $updated->status);
        $this->assertStringContainsString('Duplicate IRN', $updated->irp_response);
    }

    public function test_cancel_accepted_einvoice(): void
    {
        $submission = IndiaEInvoiceSubmission::factory()->accepted()->create([
            'organization_id' => $this->organization->id,
        ]);

        $updated = $this->service->cancel($submission, 'Issued to wrong GSTIN');
        $this->assertEquals(IndiaEInvoiceSubmission::STATUS_CANCELLED, $updated->status);
        $this->assertStringContainsString('wrong GSTIN', $updated->cancel_reason);
        $this->assertNotNull($updated->cancelled_at);
    }

    public function test_cancel_throws_for_non_accepted_submission(): void
    {
        $submission = IndiaEInvoiceSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => IndiaEInvoiceSubmission::STATUS_PENDING,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->cancel($submission, 'Error');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function sampleData(array $overrides = []): array
    {
        return array_merge([
            'organization_id'  => $this->organization->id,
            'document_number'  => 'INV-2025-001',
            'document_type'    => 'INV',
            'document_date'    => '01/04/2025',
            'gstin_seller'     => '27AAPFU0939F1ZV',
            'gstin_buyer'      => '07AACCS7531C1ZJ',
            'seller_name'      => 'Acme India Pvt Ltd',
            'buyer_name'       => 'Buyer Corp Delhi',
            'seller_state_code' => '27',
            'buyer_state_code'  => '07',
            'supply_type'      => 'B2B',
            'taxable_value'    => 100_000.0,
            'cgst_amount'      => 9_000.0,
            'sgst_amount'      => 9_000.0,
            'igst_amount'      => 0.0,
            'cess_amount'      => 0.0,
            'total_amount'     => 118_000.0,
            'items'            => [
                [
                    'description'    => 'Software Services',
                    'quantity'       => 1,
                    'unit_price'     => 100_000,
                    'total_amount'   => 100_000,
                    'taxable_amount' => 100_000,
                    'gst_rate'       => 18,
                    'cgst_amount'    => 9_000,
                    'sgst_amount'    => 9_000,
                    'igst_amount'    => 0,
                    'cess_amount'    => 0,
                    'total_with_tax' => 118_000,
                    'is_service'     => 'Y',
                    'hsn_code'       => '998314',
                ],
            ],
        ], $overrides);
    }
}
