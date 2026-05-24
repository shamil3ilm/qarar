<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Parses ISO 20022 CAMT.053 bank statement XML files.
 *
 * Supported versions: camt.053.001.02 and camt.053.001.06
 */
class Camt053Parser
{
    private const NS_V2 = 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.02';
    private const NS_V6 = 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.06';

    /**
     * @return array{
     *   account_number: string,
     *   statement_number: string,
     *   opening_balance: array{indicator: string, date: string, currency: string, amount: float},
     *   closing_balance: array{indicator: string, date: string, currency: string, amount: float},
     *   transactions: list<array{value_date: string, booking_date: string, indicator: string, amount: float, transaction_type: string, reference: string, narrative: string}>
     * }
     *
     * @throws InvalidArgumentException When XML is malformed or not a CAMT.053 document.
     */
    public function parse(string $content): array
    {
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($content);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $msg = !empty($errors) ? $errors[0]->message : 'Unknown XML error';
            throw new InvalidArgumentException("Invalid XML: {$msg}");
        }

        $ns   = $this->detectNamespace($xml);
        $xml->registerXPathNamespace('c', $ns);

        $stmts = $xml->xpath('//c:BkToCstmrStmt/c:Stmt');

        if (empty($stmts)) {
            throw new InvalidArgumentException('No BkToCstmrStmt/Stmt element found in CAMT.053 document.');
        }

        $stmt = $stmts[0];
        $stmt->registerXPathNamespace('c', $ns);

        $accountNumber = $this->extractAccountNumber($stmt);
        $statementId   = (string) ($stmt->Id ?? '');

        return [
            'account_number'   => $accountNumber,
            'statement_number' => $statementId,
            'opening_balance'  => $this->findBalance($stmt, 'OPBD', $ns),
            'closing_balance'  => $this->findBalance($stmt, 'CLBD', $ns),
            'transactions'     => $this->parseEntries($stmt, $ns),
        ];
    }

    private function detectNamespace(SimpleXMLElement $xml): string
    {
        $namespaces = $xml->getNamespaces(true);

        foreach ($namespaces as $ns) {
            if (str_contains($ns, 'camt.053')) {
                return $ns;
            }
        }

        // Default to v2 if detection fails
        return self::NS_V2;
    }

    private function extractAccountNumber(SimpleXMLElement $stmt): string
    {
        $iban  = (string) ($stmt->Acct->Id->IBAN ?? '');
        $other = (string) ($stmt->Acct->Id->Othr->Id ?? '');

        return $iban ?: $other;
    }

    /**
     * @return array{indicator: string, date: string, currency: string, amount: float}
     */
    private function findBalance(SimpleXMLElement $stmt, string $typeCode, string $ns): array
    {
        foreach ($stmt->Bal ?? [] as $bal) {
            $code = (string) ($bal->Tp->CdOrPrtry->Cd ?? '');

            if ($code !== $typeCode) {
                continue;
            }

            $cdi = (string) ($bal->CdtDbtInd ?? 'CRDT');

            return [
                'indicator' => $cdi === 'CRDT' ? 'C' : 'D',
                'date'      => (string) ($bal->Dt->Dt ?? $bal->Dt->DtTm ?? ''),
                'currency'  => (string) ($bal->Amt->attributes()['Ccy'] ?? ''),
                'amount'    => (float) ($bal->Amt ?? 0),
            ];
        }

        return ['indicator' => 'C', 'date' => '', 'currency' => '', 'amount' => 0.0];
    }

    /**
     * @return list<array{value_date: string, booking_date: string, indicator: string, amount: float, transaction_type: string, reference: string, narrative: string}>
     */
    private function parseEntries(SimpleXMLElement $stmt, string $ns): array
    {
        $transactions = [];

        foreach ($stmt->Ntry ?? [] as $entry) {
            $cdi         = (string) ($entry->CdtDbtInd ?? 'CRDT');
            $bookingDate = (string) ($entry->BookgDt->Dt ?? $entry->BookgDt->DtTm ?? '');
            $valueDate   = (string) ($entry->ValDt->Dt ?? $entry->ValDt->DtTm ?? $bookingDate);
            $amount      = (float) ($entry->Amt ?? 0);
            $currency    = (string) ($entry->Amt->attributes()['Ccy'] ?? '');

            $narrative = '';
            $reference = (string) ($entry->AcctSvcrRef ?? '');

            foreach ($entry->NtryDtls->TxDtls ?? [] as $txDtl) {
                if (empty($narrative)) {
                    $narrative = (string) ($txDtl->RmtInf->Ustrd ?? '');
                }
                if (empty($reference)) {
                    $reference = (string) ($txDtl->Refs->EndToEndId ?? '');
                }
            }

            $transactions[] = [
                'value_date'       => $valueDate,
                'booking_date'     => $bookingDate,
                'indicator'        => $cdi === 'CRDT' ? 'C' : 'D',
                'amount'           => $amount,
                'transaction_type' => $currency,
                'reference'        => $reference,
                'narrative'        => $narrative,
            ];
        }

        return $transactions;
    }
}
