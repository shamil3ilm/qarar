<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\DirectDebitCollection;
use App\Models\Accounting\DirectDebitMandate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DirectDebitService
{
    /**
     * List mandates with optional filters.
     */
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = DirectDebitMandate::with(['counterparty:id,name'])->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new mandate.
     */
    public function create(array $data): DirectDebitMandate
    {
        return DB::transaction(static function () use ($data): DirectDebitMandate {
            if (empty($data['next_collection_date']) && !empty($data['first_collection_date'])) {
                $data['next_collection_date'] = $data['first_collection_date'];
            }

            return DirectDebitMandate::create($data);
        });
    }

    /**
     * Update a mandate.
     */
    public function update(DirectDebitMandate $mandate, array $data): DirectDebitMandate
    {
        if (!in_array($mandate->status, ['draft', 'paused'], true)) {
            throw new InvalidArgumentException('Mandate can only be updated when in draft or paused status.');
        }

        DB::transaction(static fn () => $mandate->update($data));

        return $mandate->fresh();
    }

    /**
     * Activate a mandate.
     */
    public function activate(DirectDebitMandate $mandate): DirectDebitMandate
    {
        if ($mandate->status !== 'draft') {
            throw new InvalidArgumentException('Only draft mandates can be activated.');
        }

        $mandate->update(['status' => 'active']);

        return $mandate->fresh();
    }

    /**
     * Pause an active mandate.
     */
    public function pause(DirectDebitMandate $mandate): DirectDebitMandate
    {
        if ($mandate->status !== 'active') {
            throw new InvalidArgumentException('Only active mandates can be paused.');
        }

        $mandate->update(['status' => 'paused']);

        return $mandate->fresh();
    }

    /**
     * Cancel a mandate.
     */
    public function cancel(DirectDebitMandate $mandate): DirectDebitMandate
    {
        if (in_array($mandate->status, ['cancelled', 'expired'], true)) {
            throw new InvalidArgumentException('Mandate is already cancelled or expired.');
        }

        $mandate->update([
            'status'            => 'cancelled',
            'cancellation_date' => now()->toDateString(),
        ]);

        return $mandate->fresh();
    }

    /**
     * Generate collection records for all due mandates in an organisation.
     */
    public function generateCollections(int $orgId): array
    {
        $due = DirectDebitMandate::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->due()
            ->get();

        $created = [];

        foreach ($due as $mandate) {
            $collection = DB::transaction(function () use ($mandate): DirectDebitCollection {
                $amount = $mandate->amount ?? 0;

                $collection = DirectDebitCollection::create([
                    'organization_id'         => $mandate->organization_id,
                    'direct_debit_mandate_id' => $mandate->id,
                    'collection_date'         => $mandate->next_collection_date,
                    'amount'                  => $amount,
                    'status'                  => 'scheduled',
                ]);

                // Advance the mandate
                $nextDate = $mandate->calculateNextDate();
                $totalCollections = $mandate->total_collections + 1;
                $newStatus = $mandate->status;

                if ($mandate->isExpired() || $nextDate === null) {
                    $newStatus = 'expired';
                }

                $mandate->update([
                    'last_collection_date' => $mandate->next_collection_date,
                    'next_collection_date' => $nextDate?->toDateString(),
                    'total_collections'    => $totalCollections,
                    'status'               => $newStatus,
                ]);

                return $collection;
            });

            $created[] = $collection;
        }

        return $created;
    }

    /**
     * Process a scheduled collection.
     */
    public function processCollection(DirectDebitCollection $collection): DirectDebitCollection
    {
        if ($collection->status !== 'scheduled') {
            throw new InvalidArgumentException('Only scheduled collections can be processed.');
        }

        $collection->update(['status' => 'submitted']);

        return $collection->fresh();
    }

    /**
     * Get all due collections for an organisation.
     */
    public function getDueCollections(int $orgId): Collection
    {
        return DirectDebitCollection::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('status', 'scheduled')
            ->whereDate('collection_date', '<=', now()->toDateString())
            ->with(['mandate:id,mandate_reference,direction'])
            ->orderBy('collection_date')
            ->get();
    }
}
