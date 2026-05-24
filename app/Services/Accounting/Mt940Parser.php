<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use InvalidArgumentException;

/**
 * Parses SWIFT MT940 bank statement files.
 *
 * Tag reference:
 *   :20:  Transaction reference
 *   :25:  Account identification
 *   :28C: Statement/sequence number
 *   :60F:/60M:  Opening balance  — [C|D] YYMMDD CCY AMOUNT(comma decimal)
 *   :61:  Statement line        — YYMMDD[MMDD][R]C/D AMOUNT SWIFT-CODE REF
 *   :86:  Information to account owner (narrative for preceding :61:)
 *   :62F:/62M:  Closing balance — same format as :60F:
 */
class Mt940Parser
{
    /**
     * @return array{
     *   account_number: string,
     *   statement_number: string,
     *   opening_balance: array{indicator: string, date: string, currency: string, amount: float},
     *   closing_balance: array{indicator: string, date: string, currency: string, amount: float},
     *   transactions: list<array{value_date: string, booking_date: string|null, indicator: string, amount: float, transaction_type: string, reference: string, narrative: string}>
     * }
     *
     * @throws InvalidArgumentException When the content cannot be parsed as MT940.
     */
    public function parse(string $content): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $blocks = $this->extractTagBlocks($content);

        if (empty($blocks)) {
            throw new InvalidArgumentException('No MT940 tags found in content.');
        }

        return [
            'account_number'   => $this->extractTag(':25:', $blocks),
            'statement_number' => $this->extractTag(':28C:', $blocks),
            'opening_balance'  => $this->parseBalance(
                $this->extractTag(':60F:', $blocks) ?: $this->extractTag(':60M:', $blocks)
            ),
            'closing_balance'  => $this->parseBalance(
                $this->extractTag(':62F:', $blocks) ?: $this->extractTag(':62M:', $blocks)
            ),
            'transactions'     => $this->parseTransactions($blocks),
        ];
    }

    /** @return list<array{tag: string, value: string}> */
    private function extractTagBlocks(string $content): array
    {
        $blocks = [];
        // Match :XX[X]: tags — greedy up to next tag or end
        preg_match_all('/^:(\d{2}[A-Z]?):(.*?)(?=^:\d{2}[A-Z]?:|\z)/ms', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $blocks[] = ['tag' => ':' . $m[1] . ':', 'value' => rtrim($m[2])];
        }

        return $blocks;
    }

    /** @param list<array{tag: string, value: string}> $blocks */
    private function extractTag(string $tag, array $blocks): string
    {
        foreach ($blocks as $b) {
            if ($b['tag'] === $tag) {
                return $b['value'];
            }
        }

        return '';
    }

    /**
     * @return array{indicator: string, date: string, currency: string, amount: float}
     */
    private function parseBalance(string $value): array
    {
        if (strlen($value) < 11) {
            return ['indicator' => 'C', 'date' => '', 'currency' => '', 'amount' => 0.0];
        }

        return [
            'indicator' => $value[0],
            'date'      => $this->parseDate6(substr($value, 1, 6)),
            'currency'  => substr($value, 7, 3),
            'amount'    => $this->parseAmount(substr($value, 10)),
        ];
    }

    /**
     * @param list<array{tag: string, value: string}> $blocks
     * @return list<array{value_date: string, booking_date: string|null, indicator: string, amount: float, transaction_type: string, reference: string, narrative: string}>
     */
    private function parseTransactions(array $blocks): array
    {
        $transactions = [];
        $pendingTx    = null;

        foreach ($blocks as $b) {
            if ($b['tag'] === ':61:') {
                if ($pendingTx !== null) {
                    $transactions[] = $pendingTx;
                }
                $pendingTx             = $this->parseStatementLine($b['value']);
                $pendingTx['narrative'] = '';
            } elseif ($b['tag'] === ':86:' && $pendingTx !== null) {
                $pendingTx['narrative'] = $b['value'];
            }
        }

        if ($pendingTx !== null) {
            $transactions[] = $pendingTx;
        }

        return $transactions;
    }

    /**
     * @return array{value_date: string, booking_date: string|null, indicator: string, amount: float, transaction_type: string, reference: string}
     */
    private function parseStatementLine(string $value): array
    {
        $valueDate = $this->parseDate6(substr($value, 0, 6));
        $pos       = 6;

        // Optional booking date MMDD — followed by C/D indicator
        $bookingDate = null;
        if (isset($value[$pos + 4]) && preg_match('/^\d{4}[CDR]/', substr($value, $pos))) {
            $mmdd        = substr($value, $pos, 4);
            $year        = substr($valueDate, 0, 4);
            $bookingDate = "{$year}-" . substr($mmdd, 0, 2) . '-' . substr($mmdd, 2, 2);
            $pos        += 4;
        }

        // D/C/RD/RC indicator (reversal credit/debit uses 2 chars)
        $indicator = $value[$pos] ?? 'C';
        $pos++;
        if (in_array($indicator, ['R', 'E'], true) && isset($value[$pos]) && in_array($value[$pos], ['C', 'D'], true)) {
            // Reversal: RC or RD — keep the second character as the real indicator
            $indicator = $value[$pos];
            $pos++;
        } elseif (isset($value[$pos]) && $value[$pos] === 'R') {
            // CR or DR suffix reversal — skip the R
            $pos++;
        }

        // Amount: digits and comma until first alpha
        preg_match('/^([\d,]+)/', substr($value, $pos), $am);
        $amount = $this->parseAmount($am[1] ?? '0');
        $pos   += strlen($am[0] ?? '');

        // Transaction type code (4 chars: NXXX)
        $txType = substr($value, $pos, 4);
        $pos   += 4;

        // Reference up to // or newline
        $rest      = substr($value, $pos);
        $firstLine = explode("\n", $rest)[0];
        $refPart   = explode('//', $firstLine, 2)[0];

        return [
            'value_date'       => $valueDate,
            'booking_date'     => $bookingDate,
            'indicator'        => $indicator,
            'amount'           => $amount,
            'transaction_type' => trim($txType),
            'reference'        => trim($refPart),
        ];
    }

    private function parseDate6(string $yymmdd): string
    {
        if (strlen($yymmdd) !== 6) {
            return '';
        }

        $yy   = (int) substr($yymmdd, 0, 2);
        $year = $yy >= 50 ? 1900 + $yy : 2000 + $yy;

        return sprintf('%04d-%02d-%02d', $year, (int) substr($yymmdd, 2, 2), (int) substr($yymmdd, 4, 2));
    }

    private function parseAmount(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }
}
