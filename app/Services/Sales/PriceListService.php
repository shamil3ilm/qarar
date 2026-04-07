<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use App\Models\Sales\PriceList;
use App\Models\Sales\PriceListAssignment;
use App\Models\Sales\PriceListItem;
use App\Models\Sales\PriceVolumeBreak;
use Illuminate\Support\Facades\DB;

class PriceListService
{
    /**
     * Create a new price list together with optional line items.
     *
     * @param  array{name:string,code:string,currency_code:string,valid_from:string,valid_to?:string,is_default?:bool,description?:string,is_active?:bool,created_by:int,organization_id:int,items?:array}  $data
     */
    public function createPriceList(array $data): PriceList
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $priceList = PriceList::create($data);

            if (!empty($items)) {
                $this->syncItems($priceList, $items);
            }

            return $priceList->fresh();
        });
    }

    /**
     * Update an existing price list and optionally replace its items.
     *
     * @param  array{name?:string,currency_code?:string,valid_from?:string,valid_to?:string,is_default?:bool,description?:string,is_active?:bool,items?:array}  $data
     */
    public function updatePriceList(PriceList $priceList, array $data): PriceList
    {
        return DB::transaction(function () use ($priceList, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $priceList->update($data);

            if ($items !== null) {
                $this->syncItems($priceList, $items);
            }

            return $priceList->fresh();
        });
    }

    /**
     * Resolve the best unit price for a contact/product/qty/currency combination.
     *
     * Resolution order:
     *   1. Customer-specific price list (assignment_type = contact)
     *   2. Customer-group price list (assignment_type = customer_group)
     *   3. Default / "all" price list
     *   4. Volume-break tier from any of the above
     *
     * Returns an array with resolved price details or null when no price found.
     *
     * @return array{unit_price:float,discount_pct:float,effective_price:float,source:string,price_list_id:int}|null
     */
    public function resolvePrice(
        Contact $contact,
        Product $product,
        float $quantity = 1.0,
        ?string $currency = null
    ): ?array {
        $currency = $currency ?? $contact->currency_code ?? 'SAR';
        $today    = now()->toDateString();

        // Retrieve all active, valid price lists for this organization in priority order.
        $assignmentRows = DB::table('price_list_assignments as pla')
            ->join('price_lists as pl', 'pl.id', '=', 'pla.price_list_id')
            ->where('pl.organization_id', $contact->organization_id)
            ->where('pl.is_active', true)
            ->where('pl.currency_code', $currency)
            ->where('pl.valid_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('pl.valid_to')->orWhere('pl.valid_to', '>=', $today);
            })
            ->whereNull('pl.deleted_at')
            ->select('pla.*', 'pl.id as pl_id')
            ->orderByDesc('pla.priority')
            ->get();

        // Determine the ordered candidate price list IDs based on assignment priority.
        $candidateListIds = $this->rankCandidateLists($assignmentRows, $contact);

        foreach ($candidateListIds as ['list_id' => $listId, 'source' => $source]) {
            // Try volume breaks first.
            $break = PriceVolumeBreak::where('price_list_id', $listId)
                ->where('product_id', $product->id)
                ->where('min_qty', '<=', $quantity)
                ->where(function ($q) use ($quantity) {
                    $q->whereNull('max_qty')->orWhere('max_qty', '>=', $quantity);
                })
                ->orderByDesc('min_qty')
                ->first();

            if ($break !== null) {
                return $this->buildPriceResult(
                    (float) $break->unit_price,
                    (float) $break->discount_pct,
                    $listId,
                    $source . '.volume_break'
                );
            }

            // Then try standard item price.
            $item = PriceListItem::where('price_list_id', $listId)
                ->where('product_id', $product->id)
                ->where('min_quantity', '<=', $quantity)
                ->orderByDesc('min_quantity')
                ->first();

            if ($item !== null) {
                return $this->buildPriceResult(
                    (float) $item->unit_price,
                    (float) ($item->discount_pct ?? $item->discount_percent ?? 0),
                    $listId,
                    $source
                );
            }
        }

        return null;
    }

    /**
     * Assign a price list to a specific contact (replaces any existing contact assignment).
     */
    public function assignToContact(PriceList $priceList, Contact $contact): PriceListAssignment
    {
        // Remove existing contact-level assignment for this contact on any list in this org.
        PriceListAssignment::whereIn(
            'price_list_id',
            PriceList::where('organization_id', $priceList->organization_id)->pluck('id')
        )
            ->where('assignment_type', PriceListAssignment::TYPE_CONTACT)
            ->where('assignment_id', $contact->id)
            ->delete();

        return PriceListAssignment::create([
            'price_list_id'   => $priceList->id,
            'assignment_type' => PriceListAssignment::TYPE_CONTACT,
            'assignment_id'   => $contact->id,
            'priority'        => 100,
        ]);
    }

    /**
     * Bulk-import price list items (upsert by product_id + variant_id + min_quantity).
     *
     * @param  array<int,array{product_id:int,variant_id?:int,unit_price:float,min_quantity?:float,discount_pct?:float,notes?:string}>  $rows
     */
    public function importItems(PriceList $priceList, array $rows): int
    {
        $count = 0;
        DB::transaction(function () use ($priceList, $rows, &$count) {
            foreach ($rows as $row) {
                $discountPct = $row['discount_pct'] ?? 0;
                if (bccomp((string) $discountPct, '0', 4) < 0 || bccomp((string) $discountPct, '100', 4) > 0) {
                    throw new \InvalidArgumentException('Discount percentage must be between 0 and 100.');
                }

                PriceListItem::updateOrCreate(
                    [
                        'price_list_id' => $priceList->id,
                        'product_id'    => $row['product_id'],
                        'variant_id'    => $row['variant_id'] ?? null,
                        'min_quantity'  => $row['min_quantity'] ?? 1,
                    ],
                    [
                        'unit_price'   => $row['unit_price'],
                        'discount_pct' => $discountPct,
                        'notes'        => $row['notes'] ?? null,
                    ]
                );
                $count++;
            }
        });

        return $count;
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Rank candidate price list IDs by assignment priority for the contact.
     *
     * @return array<int,array{list_id:int,source:string}>
     */
    private function rankCandidateLists(
        \Illuminate\Support\Collection $assignmentRows,
        Contact $contact
    ): array {
        $candidates = [];

        foreach ($assignmentRows as $row) {
            if (
                $row->assignment_type === PriceListAssignment::TYPE_CONTACT
                && (int) $row->assignment_id === $contact->id
            ) {
                $candidates[] = ['list_id' => (int) $row->pl_id, 'source' => 'contact', 'sort' => (int) $row->priority + 1000];
                continue;
            }

            if (
                $row->assignment_type === PriceListAssignment::TYPE_CUSTOMER_GROUP
                && $contact->customer_group_id
                && (int) $row->assignment_id === (int) $contact->customer_group_id
            ) {
                $candidates[] = ['list_id' => (int) $row->pl_id, 'source' => 'customer_group', 'sort' => (int) $row->priority + 500];
                continue;
            }

            if ($row->assignment_type === PriceListAssignment::TYPE_ALL) {
                $candidates[] = ['list_id' => (int) $row->pl_id, 'source' => 'all', 'sort' => (int) $row->priority];
            }
        }

        usort($candidates, static fn ($a, $b) => $b['sort'] <=> $a['sort']);

        return $candidates;
    }

    /**
     * Sync price list items: delete removed items, upsert new / updated ones.
     *
     * @param  array<int,array{product_id:int,variant_id?:int,unit_price:float,min_quantity?:float,discount_pct?:float,notes?:string}>  $items
     */
    private function syncItems(PriceList $priceList, array $items): void
    {
        // Delete existing items and recreate — simplest strategy for full replacement.
        PriceListItem::where('price_list_id', $priceList->id)->delete();

        foreach ($items as $item) {
            $discountPct = $item['discount_pct'] ?? 0;
            if (bccomp((string) $discountPct, '0', 4) < 0 || bccomp((string) $discountPct, '100', 4) > 0) {
                throw new \InvalidArgumentException('Discount percentage must be between 0 and 100.');
            }

            PriceListItem::create(array_merge($item, [
                'price_list_id' => $priceList->id,
                'min_quantity'  => $item['min_quantity'] ?? 1,
                'discount_pct'  => $discountPct,
            ]));
        }
    }

    /**
     * Build a standardised price resolution result array.
     */
    private function buildPriceResult(
        float $unitPrice,
        float $discountPct,
        int $priceListId,
        string $source
    ): array {
        $discountAmount  = bcdiv(bcmul((string) $unitPrice, (string) $discountPct, 4), '100', 4);
        $effectivePrice  = bcsub((string) $unitPrice, $discountAmount, 4);

        return [
            'unit_price'      => $unitPrice,
            'discount_pct'    => $discountPct,
            'effective_price' => (float) $effectivePrice,
            'source'          => $source,
            'price_list_id'   => $priceListId,
        ];
    }
}
