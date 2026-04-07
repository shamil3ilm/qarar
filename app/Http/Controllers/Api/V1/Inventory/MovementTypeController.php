<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovementTypeController extends Controller
{
    /**
     * The standard SAP-compatible movement type catalog.
     *
     * @var array<int, array<string, string>>
     */
    private const MOVEMENT_TYPES = [
        ['code' => '101', 'name' => 'Goods Receipt for Purchase Order',      'direction' => 'in',       'category' => 'goods_receipt'],
        ['code' => '102', 'name' => 'Reversal of GR for PO',                 'direction' => 'out',      'category' => 'goods_receipt'],
        ['code' => '201', 'name' => 'Goods Issue for Cost Center',           'direction' => 'out',      'category' => 'goods_issue'],
        ['code' => '202', 'name' => 'Reversal of GI for Cost Center',        'direction' => 'in',       'category' => 'goods_issue'],
        ['code' => '261', 'name' => 'Goods Issue for Production Order',      'direction' => 'out',      'category' => 'goods_issue'],
        ['code' => '262', 'name' => 'Reversal of GI for Production Order',   'direction' => 'in',       'category' => 'goods_issue'],
        ['code' => '301', 'name' => 'Transfer Posting Plant to Plant',       'direction' => 'transfer', 'category' => 'transfer'],
        ['code' => '311', 'name' => 'Transfer Posting Storage Location',     'direction' => 'transfer', 'category' => 'transfer'],
        ['code' => '501', 'name' => 'Receipt without Purchase Order',        'direction' => 'in',       'category' => 'other_receipt'],
        ['code' => '551', 'name' => 'Scrapping',                             'direction' => 'out',      'category' => 'scrap'],
        ['code' => '601', 'name' => 'Goods Issue for Delivery',              'direction' => 'out',      'category' => 'goods_issue'],
        ['code' => '701', 'name' => 'Goods Issue Physical Inventory',        'direction' => 'out',      'category' => 'inventory'],
        ['code' => '702', 'name' => 'Goods Receipt Physical Inventory',      'direction' => 'in',       'category' => 'inventory'],
    ];

    /**
     * List all movement types used in the system.
     * SAP equivalent: OMJJ (Movement Type Configuration).
     *
     * Supports optional filtering by ?category= and ?direction= query params.
     */
    public function index(Request $request): JsonResponse
    {
        $types = self::MOVEMENT_TYPES;

        $category  = $request->query('category');
        $direction = $request->query('direction');

        if ($category) {
            $types = array_values(
                array_filter($types, static fn (array $t): bool => $t['category'] === $category)
            );
        }

        if ($direction) {
            $types = array_values(
                array_filter($types, static fn (array $t): bool => $t['direction'] === $direction)
            );
        }

        return $this->success($types);
    }

    /**
     * Get stock movement statistics grouped by movement type for the organization.
     *
     * Query params:
     *   - days (int, default 30) — look-back window in days
     */
    public function statistics(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $days  = max(1, $request->integer('days', 30));

        $stats = StockMovement::where('organization_id', $orgId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('movement_type, COUNT(*) as count, SUM(ABS(quantity)) as total_quantity')
            ->groupBy('movement_type')
            ->orderByDesc('count')
            ->get();

        return $this->success([
            'period_days' => $days,
            'movements'   => $stats,
        ]);
    }
}
