<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\BankStatementImport;
use App\Models\Accounting\BankTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Electronic Bank Statement Parser
 *
 * Supports MT940 (SWIFT) and ISO 20022 CAMT.053 formats.
 * Parses raw bank statement content into structured transaction arrays
 * and persists them as BankReconciliationItems.
 */
class EbsParserService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Detect format and import the statement, returning parsed transaction count.
     */
    public function import(BankStatementImport $import, string $content): int
    {
        $transactions = match ($import->file_type) {
            'mt940'   => $this->parseMt940($content),
            'camt053' => $this->parseCamt053($content),
            default   => $this->autoDetectAndParse($content),
        };

        $this->persistTransactions($import, $transactions);

        $import->update([
            'status'                => BankStatementImport::STATUS_COMPLETED,
            'imported_transactions' => count($transactions),
            'total_transactions'    => count($transactions),
        ]);

        return count($transactions);
    }

    // -------------------------------------------------------------------------
    // MT940 Parser (SWIFT bank statement format)
    // -------------------------------------------------------------------------

    /**
     * Parse MT940 format into array of transaction arrays.
     *
     * Structure:
     *   :20: - Reference
     *   :25: - Account identification (IBAN/account number)
     *   :28C: - Statement number / sequence
     *   :60F: - Opening balance (D/C, YYMMDD, currency, amount)
     *   :61: - Transaction line (YYMMDD[MMDD], D/C, amount, ref)
     *   :86: - Transaction details / description
     *   :62F: - Closing balance
     */
    public function parseMt940(string $content): array
    {
        $transactions = [];
        $lines        = explode("\n", str_replace("\r\n", "\n", trim($content)));
        $fields       = $this->groupMt940Fields($lines);

        $iban     = $this->extractMt940Iban($fields[':25:'] ?? '');
        $currency = $this->extractMt940Currency($fields[':60F:'] ?? $fields[':60M:'] ?? '');

        // Each :61: field is one transaction; :86: immediately follows with details
        $txLines     = $fields[':61:'] ?? [];
        $detailLines = $fields[':86:'] ?? [];

        foreach ($txLines as $i => $txLine) {
            $tx = $this->parseMt940Transaction($txLine, $detailLines[$i] ?? '', $currency, $iban);
            if ($tx !== null) {
                $transactions[] = $tx;
            }
        }

        return $transactions;
    }

    // -------------------------------------------------------------------------
    // CAMT.053 Parser (ISO 20022 XML)
    // -------------------------------------------------------------------------

    /**
     * Parse CAMT.053 XML into array of transaction arrays.
     */
    public function parseCamt053(string $xmlContent): array
    {
        $transactions = [];

        $xml = @simplexml_load_string($xmlContent);
        if ($xml === false) {
            throw new RuntimeException('Invalid CAMT.053 XML content.');
        }

        $xml->registerXPathNamespace('camt', 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.02');
        $xml->registerXPathNamespace('c',    'urn:iso:std:iso:20022:tech:xsd:camt.053.001.02');

        // Walk statements
        $statements = $xml->xpath('//Stmt') ?: $xml->xpath('//*[local-name()="Stmt"]') ?: [];

        foreach ($statements as $stmt) {
            $iban     = (string) ($stmt->Acct->Id->IBAN ?? $stmt->Acct->Id->Othr->Id ?? '');
            $currency = (string) ($stmt->Acct->Ccy ?? '');

            $entries = $stmt->Ntry ?? [];
            foreach ($entries as $entry) {
                $tx = $this->parseCamt053Entry($entry, $iban, $currency);
                if ($tx !== null) {
                    $transactions[] = $tx;
                }
            }
        }

        return $transactions;
    }

    // -------------------------------------------------------------------------
    // Persistence
    // -------------------------------------------------------------------------

    private function persistTransactions(BankStatementImport $import, array $transactions): void
    {
        $batchId = 'EBS-' . $import->id . '-' . now()->format('YmdHis');

        DB::transaction(function () use ($import, $transactions, $batchId): void {
            foreach ($transactions as $tx) {
                BankTransaction::create([
                    'uuid'              => (string) \Illuminate\Support\Str::uuid(),
                    'organization_id'   => $import->organization_id,
                    'bank_account_id'   => $import->bank_account_id,
                    'transaction_date'  => $tx['date'],
                    'value_date'        => $tx['value_date'] ?? $tx['date'],
                    'description'       => $tx['description'] ?: '(no description)',
                    'reference'         => $tx['reference'] ?? null,
                    'transaction_type'  => $tx['type'],   // 'credit' | 'debit'
                    'amount'            => $tx['amount'],
                    'status'            => 'unmatched',
                    'import_source'     => $import->file_type,
                    'import_batch_id'   => $batchId,
                    'raw_data'          => $tx,
                ]);
            }
        });
    }

    // -------------------------------------------------------------------------
    // MT940 helpers
    // -------------------------------------------------------------------------

    /**
     * Group raw MT940 lines into field arrays keyed by tag (:XX:).
     * Multiple occurrences of :61: and :86: are stored as arrays.
     */
    private function groupMt940Fields(array $lines): array
    {
        $fields     = [];
        $currentTag = null;
        $buffer     = '';
        $multiTags  = [':61:', ':86:'];

        foreach ($lines as $line) {
            if (preg_match('/^(:\d{2}[A-Z]?:)(.*)$/', $line, $m)) {
                if ($currentTag !== null) {
                    $value = trim($buffer);
                    if (in_array($currentTag, $multiTags, true)) {
                        $fields[$currentTag][] = $value;
                    } else {
                        $fields[$currentTag] = $value;
                    }
                }
                $currentTag = $m[1];
                $buffer     = $m[2];
            } else {
                $buffer .= ' ' . trim($line);
            }
        }

        if ($currentTag !== null) {
            $value = trim($buffer);
            if (in_array($currentTag, $multiTags, true)) {
                $fields[$currentTag][] = $value;
            } else {
                $fields[$currentTag] = $value;
            }
        }

        return $fields;
    }

    private function extractMt940Iban(string $field25): string
    {
        // :25: may be "BANKBIC/IBAN" or just IBAN
        $parts = explode('/', $field25);
        return trim(end($parts));
    }

    private function extractMt940Currency(string $balanceField): string
    {
        // :60F: = D/CYYMMDDCCCAMOUNT  — position 7 is 3-char currency
        if (strlen($balanceField) >= 10) {
            return substr($balanceField, 7, 3);
        }
        return '';
    }

    private function parseMt940Transaction(string $txLine, string $details, string $currency, string $iban): ?array
    {
        // Format: YYMMDD[MMDD]2aCFRU/3!a[15d]NNONREF//16x
        // Date: first 6 chars YYMMDD
        // Value date: optional 4 chars MMDD
        // Credit/Debit indicator: C|D|RC|RD
        // Amount: decimal with comma separator (European format)
        if (!preg_match('/^(\d{6})(\d{4})?([RD]?[CD])(\d+,\d{0,2})(N.+?)\/\/(.*)$/', $txLine, $m)) {
            // Fallback: simplified match
            if (!preg_match('/^(\d{6})([CD])(\d+[,.]?\d*)/', $txLine, $m2)) {
                return null;
            }
            $date   = Carbon::createFromFormat('ymd', $m2[1])->toDateString();
            $type   = str_contains($m2[2], 'D') ? 'debit' : 'credit';
            $amount = (float) str_replace(',', '.', $m2[3]);
            return [
                'date'       => $date,
                'type'       => $type,
                'amount'     => $amount,
                'currency'   => $currency,
                'reference'  => '',
                'description' => trim($details),
                'iban'       => $iban,
            ];
        }

        $bookDate  = Carbon::createFromFormat('ymd', $m[1])->toDateString();
        $valueDate = !empty($m[2]) ? Carbon::createFromFormat('md', $m[2])->toDateString() : $bookDate;
        $indicator = strtoupper($m[3]);
        $type      = str_contains($indicator, 'D') ? 'debit' : 'credit';
        $amount    = (float) str_replace(',', '.', $m[4]);
        $bankRef   = trim($m[6] ?? '');

        return [
            'date'           => $bookDate,
            'value_date'     => $valueDate,
            'type'           => $type,
            'amount'         => $amount,
            'currency'       => $currency,
            'reference'      => trim($m[5] ?? ''),
            'bank_reference' => $bankRef,
            'description'    => trim($details),
            'iban'           => $iban,
        ];
    }

    // -------------------------------------------------------------------------
    // CAMT.053 helpers
    // -------------------------------------------------------------------------

    private function parseCamt053Entry(\SimpleXMLElement $entry, string $iban, string $currency): ?array
    {
        $indicator = strtoupper((string) ($entry->CdtDbtInd ?? ''));
        $type      = $indicator === 'CRDT' ? 'credit' : 'debit';
        $amount    = (float) ($entry->Amt ?? 0);
        $entryCcy  = (string) ($entry->Amt['Ccy'] ?? $currency);

        $bookDate  = (string) ($entry->BookgDt->Dt ?? $entry->BookgDt->DtTm ?? '');
        $valueDate = (string) ($entry->ValDt->Dt ?? $entry->ValDt->DtTm ?? $bookDate);

        if ($amount <= 0 || empty($bookDate)) {
            return null;
        }

        // Remittance information
        $reference   = (string) ($entry->NtryDtls->TxDtls->Refs->EndToEndId
            ?? $entry->NtryDtls->TxDtls->Refs->TxId
            ?? $entry->AcctSvcrRef
            ?? '');
        $description = (string) ($entry->NtryDtls->TxDtls->RmtInf->Ustrd
            ?? $entry->AddtlNtryInf
            ?? '');
        $bankRef     = (string) ($entry->AcctSvcrRef ?? '');

        return [
            'date'           => Carbon::parse($bookDate)->toDateString(),
            'value_date'     => Carbon::parse($valueDate)->toDateString(),
            'type'           => $type,
            'amount'         => $amount,
            'currency'       => $entryCcy,
            'reference'      => $reference,
            'bank_reference' => $bankRef,
            'description'    => $description,
            'iban'           => $iban,
        ];
    }

    // -------------------------------------------------------------------------
    // Auto-detect
    // -------------------------------------------------------------------------

    private function autoDetectAndParse(string $content): array
    {
        $trimmed = ltrim($content);

        if (str_starts_with($trimmed, '<') || str_starts_with($trimmed, '<?xml')) {
            return $this->parseCamt053($content);
        }

        if (str_contains($content, ':20:') || str_contains($content, ':60F:')) {
            return $this->parseMt940($content);
        }

        throw new RuntimeException('Unable to detect bank statement format. Supported: MT940, CAMT.053.');
    }
}
