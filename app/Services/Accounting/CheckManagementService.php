<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\CheckBook;
use App\Models\Accounting\CheckRegisterEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CheckManagementService
{
    // -------------------------------------------------------------------------
    // Check Books
    // -------------------------------------------------------------------------

    public function listBooks(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CheckBook::with(['bankAccount:id,account_name,bank_name'])->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['bank_account_id'])) {
            $query->where('bank_account_id', $filters['bank_account_id']);
        }

        return $query->paginate($perPage);
    }

    public function createBook(array $data): CheckBook
    {
        return DB::transaction(static function () use ($data): CheckBook {
            $data['current_check_number'] = $data['from_check_number'];

            return CheckBook::create($data);
        });
    }

    // -------------------------------------------------------------------------
    // Check Register Entries
    // -------------------------------------------------------------------------

    public function listChecks(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CheckRegisterEntry::with(['payee:id,name', 'checkBook:id,check_book_number'])
            ->orderByDesc('check_date');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (!empty($filters['check_type'])) {
            $query->where('check_type', $filters['check_type']);
        }

        return $query->paginate($perPage);
    }

    public function createCheck(array $data): CheckRegisterEntry
    {
        return DB::transaction(static function () use ($data): CheckRegisterEntry {
            // Auto-advance check book if linked
            if (!empty($data['check_book_id'])) {
                $book = CheckBook::lockForUpdate()->findOrFail($data['check_book_id']);

                if ($book->status !== 'active') {
                    throw new InvalidArgumentException('Check book is not active.');
                }

                if (empty($data['check_number'])) {
                    $data['check_number'] = $book->getNextCheckNumber();
                    $book->advanceCheckNumber();
                }
            }

            return CheckRegisterEntry::create($data);
        });
    }

    public function print(CheckRegisterEntry $check): CheckRegisterEntry
    {
        if (!in_array($check->status, ['draft'], true)) {
            throw new InvalidArgumentException('Only draft checks can be printed.');
        }

        $check->update(['status' => 'printed', 'printed_at' => now()]);

        return $check->fresh();
    }

    public function issue(CheckRegisterEntry $check): CheckRegisterEntry
    {
        if (!in_array($check->status, ['draft', 'printed'], true)) {
            throw new InvalidArgumentException('Only draft or printed checks can be issued.');
        }

        $check->update(['status' => 'issued', 'issued_at' => now()]);

        return $check->fresh();
    }

    public function markCleared(CheckRegisterEntry $check): CheckRegisterEntry
    {
        if (!in_array($check->status, ['issued', 'presented'], true)) {
            throw new InvalidArgumentException('Only issued or presented checks can be cleared.');
        }

        $check->update(['status' => 'cleared', 'cleared_at' => now()]);

        return $check->fresh();
    }

    public function markBounced(CheckRegisterEntry $check, string $reason): CheckRegisterEntry
    {
        if (!in_array($check->status, ['issued', 'presented', 'cleared'], true)) {
            throw new InvalidArgumentException('Check cannot be bounced from its current status.');
        }

        $check->update([
            'status'        => 'bounced',
            'bounced_at'    => now(),
            'bounce_reason' => $reason,
        ]);

        return $check->fresh();
    }

    public function cancel(CheckRegisterEntry $check): CheckRegisterEntry
    {
        if (in_array($check->status, ['cleared', 'bounced', 'cancelled'], true)) {
            throw new InvalidArgumentException('Check cannot be cancelled from its current status.');
        }

        $check->update(['status' => 'cancelled']);

        return $check->fresh();
    }

    public function getOutstandingChecks(int $orgId): Collection
    {
        return CheckRegisterEntry::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->outstanding()
            ->orderBy('check_date')
            ->get();
    }
}
