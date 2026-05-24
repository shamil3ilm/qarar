<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\PaymentFile;
use App\Models\Accounting\PaymentRun;
use App\Models\Accounting\PaymentRunItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Payment Medium Format Generator
 *
 * Generates standard payment file formats:
 *  - SEPA Credit Transfer (pain.001.001.03 / ISO 20022)
 *  - SWIFT MT103 (single customer credit transfer)
 *  - ACH NACHA (US domestic ACH batch)
 */
class PaymentFormatService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate the appropriate file content for a PaymentRun based on format.
     */
    public function generate(PaymentRun $run): string
    {
        $items = $run->items()->with('vendor')->get();

        return match (strtolower($run->payment_method ?? 'sepa')) {
            'sepa'        => $this->generateSepaXml($run, $items),
            'swift_mt103' => $this->generateSwiftBatch($run, $items),
            'ach', 'nacha' => $this->generateNacha($run, $items),
            'sarie'       => $this->generateSarie($run, $items),
            'neft'        => $this->generateNeft($run, $items),
            'rtgs'        => $this->generateRtgs($run, $items),
            default       => $this->generateSepaXml($run, $items),
        };
    }

    // -------------------------------------------------------------------------
    // SEPA Credit Transfer — pain.001.001.03
    // -------------------------------------------------------------------------

    public function generateSepaXml(PaymentRun $run, Collection $items): string
    {
        $msgId      = 'ERP-' . $run->id . '-' . now()->format('YmdHis');
        $creationDt = now()->format('Y-m-d\TH:i:s');
        $totalItems = $items->count();
        $totalAmount = $items->sum(fn($i) => (float) $i->amount);
        $currency   = $run->currency ?? 'EUR';
        $execDate   = Carbon::parse($run->payment_date)->format('Y-m-d');

        $pmtInfId   = 'PMT-' . $run->id;
        $debtorName = $run->organization->name ?? 'Company';
        $debtorIban = $run->bank_account_iban ?? '';
        $debtorBic  = $run->bank_bic ?? '';

        $txBlocks = '';
        foreach ($items as $item) {
            $txBlocks .= $this->sepaTransaction($item, $currency);
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL
            . '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' . PHP_EOL
            . '  <CstmrCdtTrfInitn>' . PHP_EOL
            . '    <GrpHdr>' . PHP_EOL
            . "      <MsgId>{$this->xmlEscape($msgId)}</MsgId>" . PHP_EOL
            . "      <CreDtTm>{$creationDt}</CreDtTm>" . PHP_EOL
            . "      <NbOfTxs>{$totalItems}</NbOfTxs>" . PHP_EOL
            . "      <CtrlSum>" . number_format($totalAmount, 2, '.', '') . "</CtrlSum>" . PHP_EOL
            . '      <InitgPty>' . PHP_EOL
            . "        <Nm>{$this->xmlEscape($debtorName)}</Nm>" . PHP_EOL
            . '      </InitgPty>' . PHP_EOL
            . '    </GrpHdr>' . PHP_EOL
            . '    <PmtInf>' . PHP_EOL
            . "      <PmtInfId>{$this->xmlEscape($pmtInfId)}</PmtInfId>" . PHP_EOL
            . '      <PmtMtd>TRF</PmtMtd>' . PHP_EOL
            . "      <NbOfTxs>{$totalItems}</NbOfTxs>" . PHP_EOL
            . "      <CtrlSum>" . number_format($totalAmount, 2, '.', '') . "</CtrlSum>" . PHP_EOL
            . '      <PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl></PmtTpInf>' . PHP_EOL
            . "      <ReqdExctnDt>{$execDate}</ReqdExctnDt>" . PHP_EOL
            . '      <Dbtr>' . PHP_EOL
            . "        <Nm>{$this->xmlEscape($debtorName)}</Nm>" . PHP_EOL
            . '      </Dbtr>' . PHP_EOL
            . '      <DbtrAcct><Id><IBAN>' . $this->xmlEscape($debtorIban) . '</IBAN></Id></DbtrAcct>' . PHP_EOL
            . '      <DbtrAgt><FinInstnId><BIC>' . $this->xmlEscape($debtorBic) . '</BIC></FinInstnId></DbtrAgt>' . PHP_EOL
            . $txBlocks
            . '    </PmtInf>' . PHP_EOL
            . '  </CstmrCdtTrfInitn>' . PHP_EOL
            . '</Document>' . PHP_EOL;
    }

    private function sepaTransaction(PaymentRunItem $item, string $currency): string
    {
        $endToEnd   = 'E2E-' . $item->id;
        $amount     = number_format((float) $item->amount, 2, '.', '');
        $credName   = $this->xmlEscape($item->vendor_name ?? $item->vendor?->name ?? 'Vendor');
        $credIban   = $this->xmlEscape($item->bank_iban ?? '');
        $credBic    = $this->xmlEscape($item->bank_bic ?? '');
        $remittance = $this->xmlEscape($item->reference ?? ('Payment ' . $item->id));

        return '      <CdtTrfTxInf>' . PHP_EOL
            . "        <PmtId><EndToEndId>{$endToEnd}</EndToEndId></PmtId>" . PHP_EOL
            . "        <Amt><InstdAmt Ccy=\"{$currency}\">{$amount}</InstdAmt></Amt>" . PHP_EOL
            . "        <CdtrAgt><FinInstnId><BIC>{$credBic}</BIC></FinInstnId></CdtrAgt>" . PHP_EOL
            . "        <Cdtr><Nm>{$credName}</Nm></Cdtr>" . PHP_EOL
            . "        <CdtrAcct><Id><IBAN>{$credIban}</IBAN></Id></CdtrAcct>" . PHP_EOL
            . "        <RmtInf><Ustrd>{$remittance}</Ustrd></RmtInf>" . PHP_EOL
            . '      </CdtTrfTxInf>' . PHP_EOL;
    }

    // -------------------------------------------------------------------------
    // SWIFT MT103 (single customer credit transfer)
    // -------------------------------------------------------------------------

    public function generateSwiftBatch(PaymentRun $run, Collection $items): string
    {
        $messages = $items->map(fn($item) => $this->generateSwiftMt103($run, $item))->implode(PHP_EOL . '-' . PHP_EOL);
        return $messages;
    }

    public function generateSwiftMt103(PaymentRun $run, PaymentRunItem $item): string
    {
        $date     = Carbon::parse($run->payment_date)->format('ymd');
        $currency = strtoupper($run->currency ?? 'USD');
        $amount   = number_format((float) $item->amount, 2, '.', '');
        $amtField = str_pad(str_replace('.', ',', $amount), 15, ' ', STR_PAD_LEFT);

        $senderBic    = strtoupper($run->bank_bic ?? 'XXXXXXXX');
        $receiverBic  = strtoupper($item->bank_bic ?? 'XXXXXXXX');
        $txRef        = 'TXN' . str_pad((string) $item->id, 13, '0', STR_PAD_LEFT);
        $bankOpCode   = 'CRED';
        $valueDate    = $date . $currency . $amtField;
        $orderingName = wordwrap($run->organization->name ?? 'COMPANY', 35, PHP_EOL . '   ', true);
        $benefName    = wordwrap($item->vendor_name ?? $item->vendor?->name ?? 'BENEFICIARY', 35, PHP_EOL . '   ', true);
        $benefAcct    = $item->bank_iban ?? $item->bank_account_number ?? 'NOTPROVIDED';
        $details      = substr($item->reference ?? 'Payment', 0, 35);

        return "{1:F01{$senderBic}0000000000}{2:I103{$receiverBic}N}{4:" . PHP_EOL
            . ":20:{$txRef}" . PHP_EOL
            . ":23B:{$bankOpCode}" . PHP_EOL
            . ":32A:{$valueDate}" . PHP_EOL
            . ":50K:{$orderingName}" . PHP_EOL
            . ":59:{$benefAcct}" . PHP_EOL
            . "   {$benefName}" . PHP_EOL
            . ":70:{$details}" . PHP_EOL
            . ":71A:OUR" . PHP_EOL
            . '-}';
    }

    // -------------------------------------------------------------------------
    // ACH NACHA (US domestic)
    // -------------------------------------------------------------------------

    public function generateNacha(PaymentRun $run, Collection $items): string
    {
        $fileCreationDate = now()->format('ymd');
        $fileCreationTime = now()->format('Hi');
        $immediateOrigin  = str_pad($run->routing_number ?? '000000000', 10);
        $immediateDestin  = str_pad($run->destination_routing ?? '000000000', 10);
        $originName       = str_pad(substr($run->organization->name ?? 'COMPANY', 0, 23), 23);
        $destinationName  = str_pad(substr($run->bank_name ?? 'BANK', 0, 23), 23);
        $fileIdModifier   = 'A';
        $blockingFactor   = '10';
        $formatCode       = '1';

        // File header (type 1)
        $fileHeader = '1'
            . ' ' . $immediateDestin . $immediateOrigin
            . $fileCreationDate . $fileCreationTime
            . $fileIdModifier . '094' . $blockingFactor . $formatCode
            . $immediateDestin . $immediateOrigin
            . str_pad('', 3);

        $batchLines      = '';
        $entryCount      = 0;
        $totalDebitAmt   = 0;
        $totalCreditAmt  = 0;
        $batchNumber     = 1;
        $batchHash       = 0;

        // Batch header (type 5)
        $serviceClass    = '200'; // mixed
        $companyName     = str_pad(substr($run->organization->name ?? 'COMPANY', 0, 16), 16);
        $companyId       = str_pad($run->company_id ?? '0000000000', 10);
        $standardEntry   = 'PPD';
        $companyEntryDesc = str_pad('PAYMENT', 10);
        $effectiveDate   = Carbon::parse($run->payment_date)->format('ymd');
        $origStatusCode  = '1';
        $originatingDfi  = str_pad(substr($run->routing_number ?? '00000000', 0, 8), 8);

        $batchHeader = '5' . $serviceClass . $companyName
            . str_pad('', 10) . $companyId . $standardEntry . $companyEntryDesc
            . str_pad('', 6) . $effectiveDate . str_pad('', 3) . $origStatusCode
            . $originatingDfi . str_pad((string) $batchNumber, 7, '0', STR_PAD_LEFT);

        foreach ($items as $item) {
            $transCode   = '22'; // checking credit
            $routingNum  = str_pad(substr($item->bank_routing ?? '000000000', 0, 9), 9);
            $checkDigit  = substr($routingNum, 8, 1);
            $routingNum  = substr($routingNum, 0, 8);
            $accountNum  = str_pad(substr($item->bank_account_number ?? '000000000000000000', 0, 17), 17);
            $amountCents = str_pad((string) (int) round((float) $item->amount * 100), 10, '0', STR_PAD_LEFT);
            $idNumber    = str_pad(substr($item->vendor_id ?? '', 0, 15), 15);
            $receiverName = str_pad(substr($item->vendor_name ?? 'VENDOR', 0, 22), 22);
            $addendaInd  = '0';
            $traceNum    = $originatingDfi . str_pad((string) ($entryCount + 1), 7, '0', STR_PAD_LEFT);

            $entry = '6' . $transCode . $routingNum . $checkDigit . $accountNum
                . $amountCents . $idNumber . $receiverName . $addendaInd . $traceNum;

            $batchLines .= $entry . PHP_EOL;
            $entryCount++;
            $totalCreditAmt += (int) round((float) $item->amount * 100);
            $batchHash      += (int) $routingNum;
        }

        // Batch control (type 8)
        $batchControl = '8' . $serviceClass
            . str_pad((string) $entryCount, 6, '0', STR_PAD_LEFT)
            . str_pad((string) ($batchHash % 10000000000), 10, '0', STR_PAD_LEFT)
            . str_pad('0000000000', 12)
            . str_pad((string) $totalCreditAmt, 12, '0', STR_PAD_LEFT)
            . $companyId . str_pad('', 39)
            . $originatingDfi . str_pad((string) $batchNumber, 7, '0', STR_PAD_LEFT);

        // File control (type 9)
        $blockCount  = (int) ceil(($entryCount + 4) / 10); // +4 for headers/controls
        $fileControl = '9'
            . str_pad('1', 6, '0', STR_PAD_LEFT) // batch count
            . str_pad((string) $blockCount, 6, '0', STR_PAD_LEFT)
            . str_pad((string) $entryCount, 8, '0', STR_PAD_LEFT)
            . str_pad((string) ($batchHash % 10000000000), 10, '0', STR_PAD_LEFT)
            . str_pad('000000000000', 12)
            . str_pad((string) $totalCreditAmt, 12, '0', STR_PAD_LEFT)
            . str_pad('', 39);

        return implode(PHP_EOL, [$fileHeader, $batchHeader, rtrim($batchLines), $batchControl, $fileControl]) . PHP_EOL;
    }

    // -------------------------------------------------------------------------
    // SARIE — Saudi Arabia Real-Time Interbank Express (ISO 20022 pain.001 localisation)
    // -------------------------------------------------------------------------

    /**
     * Generate a SARIE credit transfer file.
     *
     * SARIE (Saudi Payments) uses ISO 20022 pain.001.001.09 with Saudi-specific
     * extensions: local instrument "SARIE", SAR-denominated, Saudi IBAN (SA…).
     */
    public function generateSarie(PaymentRun $run, Collection $items): string
    {
        $msgId      = 'SARIE-' . $run->id . '-' . now()->format('YmdHis');
        $creationDt = now()->format('Y-m-d\TH:i:s');
        $execDate   = Carbon::parse($run->payment_date)->format('Y-m-d');
        $totalItems = $items->count();
        $totalAmount = $items->sum(fn ($i) => (float) $i->amount);
        $debtorName  = $this->xmlEscape($run->organization->name ?? 'Company');
        $debtorIban  = $this->xmlEscape($run->bank_account_iban ?? '');
        $debtorBic   = $this->xmlEscape($run->bank_bic ?? 'RJHISARI');

        $txBlocks = '';
        foreach ($items as $item) {
            $endToEnd    = 'SARIE-E2E-' . $item->id;
            $amount      = number_format((float) $item->amount, 2, '.', '');
            $credName    = $this->xmlEscape($item->vendor_name ?? $item->vendor?->name ?? 'Beneficiary');
            $credIban    = $this->xmlEscape($item->bank_iban ?? '');
            $credBic     = $this->xmlEscape($item->bank_bic ?? '');
            $remittance  = $this->xmlEscape($item->reference ?? 'Payment ' . $item->id);

            $txBlocks .= '      <CdtTrfTxInf>' . PHP_EOL
                . "        <PmtId><EndToEndId>{$endToEnd}</EndToEndId></PmtId>" . PHP_EOL
                . '        <PmtTpInf><LclInstrm><Cd>SARIE</Cd></LclInstrm></PmtTpInf>' . PHP_EOL
                . "        <Amt><InstdAmt Ccy=\"SAR\">{$amount}</InstdAmt></Amt>" . PHP_EOL
                . "        <CdtrAgt><FinInstnId><BICFI>{$credBic}</BICFI></FinInstnId></CdtrAgt>" . PHP_EOL
                . "        <Cdtr><Nm>{$credName}</Nm></Cdtr>" . PHP_EOL
                . "        <CdtrAcct><Id><IBAN>{$credIban}</IBAN></Id></CdtrAcct>" . PHP_EOL
                . "        <RmtInf><Ustrd>{$remittance}</Ustrd></RmtInf>" . PHP_EOL
                . '      </CdtTrfTxInf>' . PHP_EOL;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL
            . '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.09">' . PHP_EOL
            . '  <CstmrCdtTrfInitn>' . PHP_EOL
            . '    <GrpHdr>' . PHP_EOL
            . "      <MsgId>{$msgId}</MsgId>" . PHP_EOL
            . "      <CreDtTm>{$creationDt}</CreDtTm>" . PHP_EOL
            . "      <NbOfTxs>{$totalItems}</NbOfTxs>" . PHP_EOL
            . '      <CtrlSum>' . number_format($totalAmount, 2, '.', '') . '</CtrlSum>' . PHP_EOL
            . "      <InitgPty><Nm>{$debtorName}</Nm></InitgPty>" . PHP_EOL
            . '    </GrpHdr>' . PHP_EOL
            . '    <PmtInf>' . PHP_EOL
            . "      <PmtInfId>PMT-SARIE-{$run->id}</PmtInfId>" . PHP_EOL
            . '      <PmtMtd>TRF</PmtMtd>' . PHP_EOL
            . '      <PmtTpInf><SvcLvl><Cd>NURG</Cd></SvcLvl><LclInstrm><Cd>SARIE</Cd></LclInstrm></PmtTpInf>' . PHP_EOL
            . "      <ReqdExctnDt><Dt>{$execDate}</Dt></ReqdExctnDt>" . PHP_EOL
            . "      <Dbtr><Nm>{$debtorName}</Nm></Dbtr>" . PHP_EOL
            . "      <DbtrAcct><Id><IBAN>{$debtorIban}</IBAN></Id></DbtrAcct>" . PHP_EOL
            . "      <DbtrAgt><FinInstnId><BICFI>{$debtorBic}</BICFI></FinInstnId></DbtrAgt>" . PHP_EOL
            . $txBlocks
            . '    </PmtInf>' . PHP_EOL
            . '  </CstmrCdtTrfInitn>' . PHP_EOL
            . '</Document>' . PHP_EOL;
    }

    // -------------------------------------------------------------------------
    // India NEFT — National Electronic Funds Transfer (RBI format)
    // -------------------------------------------------------------------------

    /**
     * Generate an India NEFT batch file.
     *
     * Format: pipe-delimited, one record per transaction.
     * Fields: TRANSACTION_TYPE|SENDER_IFSC|SENDER_ACCOUNT|AMOUNT|BENEFICIARY_IFSC|BENEFICIARY_ACCOUNT|BENEFICIARY_NAME|PAYMENT_REF
     */
    public function generateNeft(PaymentRun $run, Collection $items): string
    {
        $lines = [];
        $batchRef = 'NEFT-' . $run->id . '-' . now()->format('YmdHis');

        // Header record
        $lines[] = implode('|', [
            'HDR',
            $batchRef,
            now()->format('d/m/Y'),
            $items->count(),
            number_format((float) $items->sum(fn ($i) => (float) $i->amount), 2, '.', ''),
            'NEFT',
            $run->bank_ifsc ?? 'SBIN0000001',
        ]);

        foreach ($items as $item) {
            $amount    = number_format((float) $item->amount, 2, '.', '');
            $ifsc      = $item->bank_ifsc ?? ($item->bank_bic ?? 'SBIN0000001');
            $acctNo    = $item->bank_account_number ?? $item->bank_iban ?? '0000000000';
            $benefName = substr($item->vendor_name ?? $item->vendor?->name ?? 'BENEFICIARY', 0, 100);
            $ref       = substr($item->reference ?? 'PMT-' . $item->id, 0, 20);

            $lines[] = implode('|', [
                'DTL',
                'NEFT',
                $run->bank_ifsc ?? 'SBIN0000001',
                $run->bank_account_number ?? '0000000000',
                $amount,
                $ifsc,
                $acctNo,
                $benefName,
                $ref,
                now()->format('d/m/Y'),
                strtoupper($run->currency ?? 'INR'),
            ]);
        }

        // Trailer record
        $lines[] = implode('|', [
            'TRL',
            $batchRef,
            $items->count(),
            number_format((float) $items->sum(fn ($i) => (float) $i->amount), 2, '.', ''),
        ]);

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    // -------------------------------------------------------------------------
    // India RTGS — Real-Time Gross Settlement (RBI format)
    // -------------------------------------------------------------------------

    /**
     * Generate an India RTGS instruction file.
     *
     * RTGS uses ISO 20022 pacs.009 / camt.054 internally in the RBI system.
     * This outputs the customer-facing instruction format (pipe-delimited)
     * compatible with corporate banking portals.
     * Minimum transaction amount: ₹2,00,000.
     */
    public function generateRtgs(PaymentRun $run, Collection $items): string
    {
        $lines = [];
        $batchRef = 'RTGS-' . $run->id . '-' . now()->format('YmdHis');

        $lines[] = implode('|', [
            'HDR',
            $batchRef,
            now()->format('d/m/Y H:i:s'),
            $items->count(),
            number_format((float) $items->sum(fn ($i) => (float) $i->amount), 2, '.', ''),
            'RTGS',
            $run->bank_ifsc ?? 'SBIN0000001',
        ]);

        foreach ($items as $item) {
            $amount    = number_format((float) $item->amount, 2, '.', '');
            $ifsc      = $item->bank_ifsc ?? ($item->bank_bic ?? 'SBIN0000001');
            $acctNo    = $item->bank_account_number ?? $item->bank_iban ?? '0000000000';
            $benefName = substr($item->vendor_name ?? $item->vendor?->name ?? 'BENEFICIARY', 0, 100);
            $ref       = substr($item->reference ?? 'PMT-' . $item->id, 0, 35);
            $acctType  = $item->bank_account_type ?? 'CA'; // CA=Current, SB=Savings

            $lines[] = implode('|', [
                'DTL',
                'RTGS',
                $run->bank_ifsc ?? 'SBIN0000001',
                $run->bank_account_number ?? '0000000000',
                $amount,
                $ifsc,
                $acctNo,
                $acctType,
                $benefName,
                $ref,
                now()->format('d/m/Y H:i:s'),
                strtoupper($run->currency ?? 'INR'),
            ]);
        }

        $lines[] = implode('|', [
            'TRL',
            $batchRef,
            $items->count(),
            number_format((float) $items->sum(fn ($i) => (float) $i->amount), 2, '.', ''),
        ]);

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
