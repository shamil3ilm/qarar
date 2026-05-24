<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\BankStatementImport;
use App\Models\Accounting\BankTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class BankStatementImportService
{
    public function __construct(
        private readonly Mt940Parser   $mt940Parser,
        private readonly Camt053Parser $camt053Parser,
    ) {}

    /**
     * Import a bank statement file (MT940 or CAMT.053) for the given bank account.
     *
     * @param array{
     *   organization_id: int,
     *   bank_account_id: int,
     *   file_name: string,
     *   file_type: string,
     *   content: string,
     *   file_path?: string|null
     * } $data
     *
     * @throws InvalidArgumentException When the file type is unsupported.
     */
    public function import(array $data, int $userId): BankStatementImport
    {
        return DB::transaction(function () use ($data, $userId): BankStatementImport {
            $import = BankStatementImport::create([
                'organization_id' => $data['organization_id'],
                'bank_account_id' => $data['bank_account_id'],
                'user_id'         => $userId,
                'file_name'       => $data['file_name'],
                'file_path'       => $data['file_path'] ?? null,
                'file_type'       => $data['file_type'],
                'status'          => BankStatementImport::STATUS_PROCESSING,
            ]);

            try {
                $parsed = $this->parse($data['file_type'], $data['content']);

                [$imported, $duplicates] = $this->persistTransactions(
                    $parsed['transactions'],
                    (int) $data['organization_id'],
                    (int) $data['bank_account_id'],
                    $data['file_type'],
                    (string) $import->id,
                );

                $import->update([
                    'statement_start_date'   => $parsed['opening_balance']['date'] ?: null,
                    'statement_end_date'     => $parsed['closing_balance']['date'] ?: null,
                    'total_transactions'     => count($parsed['transactions']),
                    'imported_transactions'  => $imported,
                    'duplicate_transactions' => $duplicates,
                    'status'                 => BankStatementImport::STATUS_COMPLETED,
                ]);
            } catch (Throwable $e) {
                $import->markFailed(['message' => $e->getMessage()]);
            }

            return $import->fresh();
        });
    }

    /** @return array{0: int, 1: int} [imported, duplicates] */
    private function persistTransactions(
        array $transactions,
        int $organizationId,
        int $bankAccountId,
        string $fileType,
        string $batchId,
    ): array {
        $imported   = 0;
        $duplicates = 0;

        foreach ($transactions as $tx) {
            $isDuplicate = BankTransaction::where('bank_account_id', $bankAccountId)
                ->where('transaction_date', $tx['value_date'])
                ->where('amount', $tx['amount'])
                ->where('reference', $tx['reference'])
                ->exists();

            if ($isDuplicate) {
                $duplicates++;
                continue;
            }

            BankTransaction::create([
                'organization_id'  => $organizationId,
                'bank_account_id'  => $bankAccountId,
                'transaction_date' => $tx['value_date'],
                'value_date'       => $tx['booking_date'] ?? $tx['value_date'],
                'reference'        => $tx['reference'] ?: null,
                'description'      => $tx['narrative'] ?? '',
                'transaction_type' => $tx['indicator'] === 'C' ? 'credit' : 'debit',
                'amount'           => $tx['amount'],
                'status'           => BankTransaction::STATUS_UNMATCHED,
                'import_source'    => $fileType,
                'import_batch_id'  => $batchId,
            ]);

            $imported++;
        }

        return [$imported, $duplicates];
    }

    private function parse(string $fileType, string $content): array
    {
        return match ($fileType) {
            BankStatementImport::FILE_TYPE_MT940   => $this->mt940Parser->parse($content),
            BankStatementImport::FILE_TYPE_CAMT053 => $this->camt053Parser->parse($content),
            default => throw new InvalidArgumentException(
                "Unsupported file type '{$fileType}'. Supported: mt940, camt053."
            ),
        };
    }
}
