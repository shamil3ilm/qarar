<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Tax\IndiaEInvoiceSubmission;

/**
 * India IRP E-Invoice JSON Builder.
 *
 * Generates the e-invoice JSON payload per the NIC / GSTN schema (v1.03)
 * and computes the IRN (Invoice Reference Number).
 *
 * IRN formula (per GSTN specification):
 *   IRN = SHA-256( GSTIN_SELLER + "/" + DOC_TYPE + "/" + DOC_NUMBER + "/" + DOC_DATE )
 *   Result: 64-character lowercase hex string.
 *
 * e-invoice JSON structure (key sections):
 *   Version, TranDtls, DocDtls, SellerDtls, BuyerDtls, ItemList, ValDtls
 */
class IndiaIrnBuilder
{
    /** NIC e-invoice schema version */
    private const SCHEMA_VERSION = '1.1';

    /**
     * Compute the IRN for a document.
     *
     * @param  string  $gstinSeller  15-character GSTIN
     * @param  string  $docType      'INV' | 'CRN' | 'DBN'
     * @param  string  $docNumber    Invoice / credit note / debit note number
     * @param  string  $docDate      Date in DD/MM/YYYY format (as required by GSTN)
     */
    public function computeIrn(
        string $gstinSeller,
        string $docType,
        string $docNumber,
        string $docDate,
    ): string {
        $hashInput = implode('/', [$gstinSeller, $docType, $docNumber, $docDate]);
        return hash('sha256', $hashInput);
    }

    /**
     * Build the full e-invoice JSON payload (NIC schema v1.1).
     *
     * @param  array<string, mixed>  $data  Invoice data
     * @return array<string, mixed>  Structured e-invoice payload
     */
    public function buildPayload(array $data): array
    {
        $docDate  = $data['document_date'] ?? date('d/m/Y');    // GSTN format: DD/MM/YYYY
        $docType  = $data['document_type'] ?? IndiaEInvoiceSubmission::DOC_TYPE_INVOICE;
        $gstinSeller = $data['gstin_seller'] ?? '';

        $irn = $this->computeIrn($gstinSeller, $docType, $data['document_number'] ?? '', $docDate);

        return [
            'Version'  => self::SCHEMA_VERSION,
            'TranDtls' => [
                'TaxSch'   => 'GST',
                'SupTyp'   => $data['supply_type'] ?? 'B2B',
                'RegRev'   => $data['reverse_charge'] ?? 'N',
                'EcmGstin' => null,
            ],
            'DocDtls' => [
                'Typ' => $docType,
                'No'  => $data['document_number'] ?? '',
                'Dt'  => $docDate,
            ],
            'SellerDtls' => [
                'Gstin'   => $gstinSeller,
                'LglNm'   => $data['seller_name'] ?? '',
                'TrdNm'   => $data['seller_trade_name'] ?? $data['seller_name'] ?? '',
                'Addr1'   => $data['seller_address'] ?? '',
                'Loc'     => $data['seller_city'] ?? '',
                'Pin'     => $data['seller_pincode'] ?? '',
                'Stcd'    => $data['seller_state_code'] ?? '',
                'Ph'      => $data['seller_phone'] ?? null,
                'Em'      => $data['seller_email'] ?? null,
            ],
            'BuyerDtls' => [
                'Gstin' => $data['gstin_buyer'] ?? 'URP',  // 'URP' = Unregistered Person
                'LglNm' => $data['buyer_name'] ?? '',
                'TrdNm' => $data['buyer_trade_name'] ?? $data['buyer_name'] ?? '',
                'Pos'   => $data['place_of_supply'] ?? $data['buyer_state_code'] ?? '',
                'Addr1' => $data['buyer_address'] ?? '',
                'Loc'   => $data['buyer_city'] ?? '',
                'Pin'   => $data['buyer_pincode'] ?? '',
                'Stcd'  => $data['buyer_state_code'] ?? '',
                'Ph'    => $data['buyer_phone'] ?? null,
                'Em'    => $data['buyer_email'] ?? null,
            ],
            'ItemList' => $this->buildItemList($data['items'] ?? []),
            'ValDtls'  => [
                'AssVal'  => (float) ($data['taxable_value'] ?? 0),
                'CgstVal' => (float) ($data['cgst_amount']   ?? 0),
                'SgstVal' => (float) ($data['sgst_amount']   ?? 0),
                'IgstVal' => (float) ($data['igst_amount']   ?? 0),
                'CesVal'  => (float) ($data['cess_amount']   ?? 0),
                'TotInvVal' => (float) ($data['total_amount'] ?? 0),
            ],
            'EwbDtls' => null,  // E-way bill details (separate flow)
            '_irn'    => $irn,  // Pre-computed; IRP will validate and override
        ];
    }

    /**
     * Build a QR code data string for the signed e-invoice.
     * Encodes key fields as base64-JSON (simplified; IRP provides the signed QR).
     */
    public function buildQrData(array $data, string $irn): string
    {
        $qrPayload = [
            'SellerGstin' => $data['gstin_seller'] ?? '',
            'BuyerGstin'  => $data['gstin_buyer'] ?? 'URP',
            'DocNo'       => $data['document_number'] ?? '',
            'DocTyp'      => $data['document_type'] ?? 'INV',
            'DocDt'       => $data['document_date'] ?? date('d/m/Y'),
            'TotInvVal'   => (float) ($data['total_amount'] ?? 0),
            'ItemCnt'     => count($data['items'] ?? []),
            'Irn'         => $irn,
        ];

        return base64_encode(json_encode($qrPayload, JSON_UNESCAPED_UNICODE));
    }

    // -------------------------------------------------------------------------

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildItemList(array $items): array
    {
        return array_values(array_map(function ($item, $index) {
            return [
                'SlNo'     => (string) ($index + 1),
                'PrdDesc'  => $item['description'] ?? '',
                'IsServc'  => $item['is_service'] ?? 'N',
                'HsnCd'    => $item['hsn_code'] ?? '',
                'Qty'      => (float) ($item['quantity'] ?? 1),
                'Unit'     => $item['unit'] ?? 'NOS',
                'UnitPrice' => (float) ($item['unit_price'] ?? 0),
                'TotAmt'   => (float) ($item['total_amount'] ?? 0),
                'AssAmt'   => (float) ($item['taxable_amount'] ?? $item['total_amount'] ?? 0),
                'GstRt'    => (float) ($item['gst_rate'] ?? 18),
                'IgstAmt'  => (float) ($item['igst_amount'] ?? 0),
                'CgstAmt'  => (float) ($item['cgst_amount'] ?? 0),
                'SgstAmt'  => (float) ($item['sgst_amount'] ?? 0),
                'CesRt'    => (float) ($item['cess_rate'] ?? 0),
                'CesAmt'   => (float) ($item['cess_amount'] ?? 0),
                'TotItemVal' => (float) ($item['total_with_tax'] ?? $item['total_amount'] ?? 0),
            ];
        }, $items, array_keys($items)));
    }
}
