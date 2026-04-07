<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\CostElement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CostElementService
{
    // ----------------------------------------------------------------
    // CRUD
    // ----------------------------------------------------------------

    public function index(array $filters): LengthAwarePaginator
    {
        $query = CostElement::query()->orderBy('code');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if (isset($filters['element_type'])) {
            $query->where('element_type', $filters['element_type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (isset($filters['cost_element_category'])) {
            $query->where('cost_element_category', $filters['cost_element_category']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->with('glAccount:id,code,name')->paginate($perPage);
    }

    public function store(array $data): CostElement
    {
        return DB::transaction(function () use ($data): CostElement {
            $this->validateGlAccount($data);

            return CostElement::create($data);
        });
    }

    public function update(CostElement $costElement, array $data): CostElement
    {
        return DB::transaction(function () use ($costElement, $data): CostElement {
            $merged = array_merge($costElement->toArray(), $data);
            $this->validateGlAccount($merged);

            $costElement->update($data);

            return $costElement->fresh(['glAccount:id,code,name']);
        });
    }

    public function destroy(CostElement $costElement): void
    {
        $costElement->delete();
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Primary cost elements must have a GL account; secondary must not require one.
     */
    private function validateGlAccount(array $data): void
    {
        $type = $data['element_type'] ?? null;

        if ($type === CostElement::TYPE_PRIMARY && empty($data['gl_account_id'])) {
            throw new InvalidArgumentException(
                'Primary cost elements must have a GL account assigned.'
            );
        }

        if (!empty($data['gl_account_id'])) {
            $exists = Account::withoutGlobalScopes()
                ->where('id', $data['gl_account_id'])
                ->exists();

            if (!$exists) {
                throw new InvalidArgumentException(
                    "GL account [{$data['gl_account_id']}] not found."
                );
            }
        }
    }
}
