<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\PaymentFile;
use App\Models\Accounting\PaymentRun;
use App\Models\Accounting\PaymentRunItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentFileService
{
    /**
     * Generate a SEPA Credit Transfer XML file for a payment run.
     */
    public function generateSepaXml(PaymentRun $run): PaymentFile
    {
        return $this->generateIso20022($run, PaymentFile::FORMAT_SEPA_CT);
    }

    /**
     * Generate an ISO 20022 payment file for a payment run.
     */
    public function generateIso20022(PaymentRun $run, string $format): PaymentFile
    {
        $allowedFormats = [
            PaymentFile::FORMAT_SEPA_CT,
            PaymentFile::FORMAT_SEPA_DD,
            PaymentFile::FORMAT_ISO20022,
            PaymentFile::FORMAT_ACH,
            PaymentFile::FORMAT_BACS,
        ];

        if (!in_array($format, $allowedFormats, true)) {
            throw new InvalidArgumentException("Unsupported payment file format: {$format}");
        }

        $items = $run->includedItems()->get();

        if ($items->isEmpty()) {
            throw new InvalidArgumentException('Payment run has no included items to generate a file for.');
        }

        $payments = $items->map(fn (PaymentRunItem $item) => [
            'id'            => $item->id,
            'amount'        => $item->payment_amount,
            'currency'      => $run->bankAccount?->currency_code ?? 'EUR',
            'reference'     => $item->document_reference ?? (string) $item->id,
            'creditor_name' => $item->vendor_name ?? 'Vendor',
            'iban'          => $item->iban ?? '',
            'bic'           => $item->bic ?? '',
        ])->toArray();

        $messageId       = 'MSG-' . $run->id . '-' . now()->format('YmdHis');
        $creationDatetime = now();
        $totalAmount     = $items->sum('payment_amount');
        $currency        = $run->bankAccount?->currency_code ?? 'EUR';

        $xmlContent = match ($format) {
            PaymentFile::FORMAT_SWIFT_MT103 => $this->buildSwiftMt103Batch($items, $run),
            PaymentFile::FORMAT_ACH, PaymentFile::FORMAT_BACS => $this->buildNacha($run, $items),
            default => $this->buildSepaCtXml($payments, $messageId, $creationDatetime->toIso8601String(), $currency),
        };

        return DB::transaction(function () use (
            $run,
            $format,
            $messageId,
            $creationDatetime,
            $xmlContent,
            $totalAmount,
            $currency,
            $items
        ): PaymentFile {
            return PaymentFile::create([
                'organization_id'        => $run->organization_id,
                'payment_run_id'         => $run->id,
                'file_format'            => $format,
                'file_name'              => "{$format}_{$run->id}_{$creationDatetime->format('Ymd_His')}.xml",
                'file_content'           => $xmlContent,
                'message_id'             => $messageId,
                'creation_datetime'      => $creationDatetime,
                'number_of_transactions' => $items->count(),
                'total_amount'           => $totalAmount,
                'currency_code'          => $currency,
                'status'                 => PaymentFile::STATUS_GENERATED,
                'created_by'             => auth()->id(),
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // SWIFT MT103
    // -------------------------------------------------------------------------

    public function buildSwiftMt103Batch(\Illuminate\Support\Collection $items, PaymentRun $run): string
    {
        $messages = [];
        foreach ($items as $item) {
            $messages[] = $this->buildSwiftMt103Single($run, $item);
        }
        return implode(PHP_EOL . '-' . PHP_EOL, $messages);
    }

    private function buildSwiftMt103Single(PaymentRun $run, PaymentRunItem $item): string
    {
        $date        = \Carbon\Carbon::parse($run->payment_date ?? now())->format('ymd');
        $currency    = strtoupper($run->bankAccount?->currency_code ?? 'USD');
        $amount      = number_format((float) ($item->payment_amount ?? $item->amount ?? 0), 2, ',', '');
        $senderBic   = strtoupper($run->bankAccount?->bank_swift_code ?? 'XXXXXXXX');
        $receiverBic = strtoupper($item->bic ?? 'XXXXXXXX');
        $txRef       = 'TXN' . str_pad((string) $item->id, 13, '0', STR_PAD_LEFT);
        $valueAmt    = $date . $currency . str_pad($amount, 15, ' ', STR_PAD_LEFT);
        $credName    = wordwrap(substr($item->vendor_name ?? 'BENEFICIARY', 0, 35), 35, "\n   ", true);
        $credAcct    = $item->iban ?? 'NOTPROVIDED';
        $details     = substr($item->document_reference ?? ('Payment ' . $item->id), 0, 35);

        return "{1:F01{$senderBic}0000000000}{2:I103{$receiverBic}N}{4:\n"
            . ":20:{$txRef}\n"
            . ":23B:CRED\n"
            . ":32A:{$valueAmt}\n"
            . ":50K:" . ($run->organization->name ?? 'COMPANY') . "\n"
            . ":59:{$credAcct}\n   {$credName}\n"
            . ":70:{$details}\n"
            . ":71A:OUR\n-}";
    }

    // -------------------------------------------------------------------------
    // ACH NACHA
    // -------------------------------------------------------------------------

    public function buildNacha(PaymentRun $run, \Illuminate\Support\Collection $items): string
    {
        $creDate     = now()->format('ymd');
        $creTime     = now()->format('Hi');
        $originRtn   = str_pad(substr($run->routing_number ?? '000000000', 0, 9), 10);
        $destRtn     = str_pad(substr($run->destination_routing ?? '000000000', 0, 9), 10);
        $orgName     = str_pad(substr($run->organization->name ?? 'COMPANY', 0, 23), 23);
        $fileHeader  = '1 ' . $destRtn . $originRtn . $creDate . $creTime . 'A094101' . $orgName . str_pad('', 3);

        $batchNum    = 1;
        $companyName = str_pad(substr($run->organization->name ?? 'COMPANY', 0, 16), 16);
        $companyId   = str_pad($run->company_id ?? '0000000000', 10);
        $effectDate  = \Carbon\Carbon::parse($run->payment_date ?? now())->format('ymd');
        $origDfi     = str_pad(substr($run->routing_number ?? '00000000', 0, 8), 8);
        $batchHeader = '5200' . $companyName . str_pad('', 10) . $companyId . 'PPD' . str_pad('PAYMENT', 10) . str_pad('', 6) . $effectDate . str_pad('', 3) . '1' . $origDfi . str_pad((string) $batchNum, 7, '0', STR_PAD_LEFT);

        $entries = '';
        $count   = 0;
        $hash    = 0;
        $total   = 0;

        foreach ($items as $item) {
            $rtn     = str_pad(substr($item->bank_routing_number ?? '00000000', 0, 8), 8);
            $chk     = substr($item->bank_routing_number ?? '0', -1, 1);
            $acct    = str_pad(substr($item->bank_account_number ?? '000000000000000000', 0, 17), 17);
            $amt     = str_pad((string) (int) round((float) ($item->payment_amount ?? 0) * 100), 10, '0', STR_PAD_LEFT);
            $idNum   = str_pad(substr((string) $item->id, 0, 15), 15);
            $name    = str_pad(substr($item->vendor_name ?? 'VENDOR', 0, 22), 22);
            $trace   = $origDfi . str_pad((string) ($count + 1), 7, '0', STR_PAD_LEFT);
            $entries .= '622' . $rtn . $chk . $acct . $amt . $idNum . $name . '0' . $trace . "\n";
            $count++;
            $hash  += (int) $rtn;
            $total += (int) round((float) ($item->payment_amount ?? 0) * 100);
        }

        $batchControl = '8200'
            . str_pad((string) $count, 6, '0', STR_PAD_LEFT)
            . str_pad((string) ($hash % 10000000000), 10, '0', STR_PAD_LEFT)
            . str_pad('000000000000', 12)
            . str_pad((string) $total, 12, '0', STR_PAD_LEFT)
            . $companyId . str_pad('', 39) . $origDfi . str_pad((string) $batchNum, 7, '0', STR_PAD_LEFT);

        $blocks      = (int) ceil(($count + 4) / 10);
        $fileControl = '9'
            . str_pad('1', 6, '0', STR_PAD_LEFT)
            . str_pad((string) $blocks, 6, '0', STR_PAD_LEFT)
            . str_pad((string) $count, 8, '0', STR_PAD_LEFT)
            . str_pad((string) ($hash % 10000000000), 10, '0', STR_PAD_LEFT)
            . str_pad('000000000000', 12)
            . str_pad((string) $total, 12, '0', STR_PAD_LEFT)
            . str_pad('', 39);

        return implode("\n", [$fileHeader, $batchHeader, rtrim($entries), $batchControl, $fileControl]) . "\n";
    }

    /**
     * Build a SEPA Credit Transfer XML (pain.001.001.03) string.
     */
    public function buildSepaCtXml(
        array $payments,
        string $messageId,
        string $creationDateTime,
        string $currency = 'EUR'
    ): string {
        $nbOfTxs  = count($payments);
        $ctrlSum  = array_sum(array_column($payments, 'amount'));
        $ctrlSum  = number_format($ctrlSum, 2, '.', '');

        $txBlocks = '';
        foreach ($payments as $pmt) {
            $amount  = number_format((float) $pmt['amount'], 2, '.', '');
            $endToEndId = htmlspecialchars((string) $pmt['reference'], ENT_XML1 | ENT_ENTS, 'UTF-8');
            $credName   = htmlspecialchars((string) $pmt['creditor_name'], ENT_XML1 | ENT_ENTS, 'UTF-8');
            $iban       = htmlspecialchars((string) $pmt['iban'], ENT_XML1 | ENT_ENTS, 'UTF-8');
            $bic        = htmlspecialchars((string) $pmt['bic'], ENT_XML1 | ENT_ENTS, 'UTF-8');

            $txBlocks .= <<<XML

                <CdtTrfTxInf>
                    <PmtId>
                        <EndToEndId>{$endToEndId}</EndToEndId>
                    </PmtId>
                    <Amt>
                        <InstdAmt Ccy="{$currency}">{$amount}</InstdAmt>
                    </Amt>
                    <CdtrAgt>
                        <FinInstnId>
                            <BIC>{$bic}</BIC>
                        </FinInstnId>
                    </CdtrAgt>
                    <Cdtr>
                        <Nm>{$credName}</Nm>
                    </Cdtr>
                    <CdtrAcct>
                        <Id>
                            <IBAN>{$iban}</IBAN>
                        </Id>
                    </CdtrAcct>
                </CdtTrfTxInf>
XML;
        }

        $msgId   = htmlspecialchars($messageId, ENT_XML1 | ENT_ENTS, 'UTF-8');
        $creDtTm = htmlspecialchars($creationDateTime, ENT_XML1 | ENT_ENTS, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <CstmrCdtTrfInitn>
        <GrpHdr>
            <MsgId>{$msgId}</MsgId>
            <CreDtTm>{$creDtTm}</CreDtTm>
            <NbOfTxs>{$nbOfTxs}</NbOfTxs>
            <CtrlSum>{$ctrlSum}</CtrlSum>
            <InitgPty>
                <Nm>ERP System</Nm>
            </InitgPty>
        </GrpHdr>
        <PmtInf>
            <PmtInfId>{$msgId}-PMT</PmtInfId>
            <PmtMtd>TRF</PmtMtd>
            <NbOfTxs>{$nbOfTxs}</NbOfTxs>
            <CtrlSum>{$ctrlSum}</CtrlSum>
            <PmtTpInf>
                <SvcLvl>
                    <Cd>SEPA</Cd>
                </SvcLvl>
            </PmtTpInf>
            <ReqdExctnDt>{$this->isoDate()}</ReqdExctnDt>
            <Dbtr>
                <Nm>Organization</Nm>
            </Dbtr>
            <DbtrAcct>
                <Id>
                    <IBAN/>
                </Id>
            </DbtrAcct>
            <DbtrAgt>
                <FinInstnId>
                    <BIC/>
                </FinInstnId>
            </DbtrAgt>{$txBlocks}
        </PmtInf>
    </CstmrCdtTrfInitn>
</Document>
XML;
    }

    /**
     * Mark a payment file as submitted.
     */
    public function markSubmitted(PaymentFile $file): void
    {
        if ($file->status !== PaymentFile::STATUS_GENERATED) {
            throw new InvalidArgumentException('Only generated files can be marked as submitted.');
        }

        $file->update([
            'status'       => PaymentFile::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Mark a payment file as acknowledged.
     */
    public function markAcknowledged(PaymentFile $file): void
    {
        if ($file->status !== PaymentFile::STATUS_SUBMITTED) {
            throw new InvalidArgumentException('Only submitted files can be acknowledged.');
        }

        $file->update([
            'status'           => PaymentFile::STATUS_ACKNOWLEDGED,
            'acknowledged_at'  => now(),
        ]);
    }

    private function isoDate(): string
    {
        return now()->toDateString();
    }
}
