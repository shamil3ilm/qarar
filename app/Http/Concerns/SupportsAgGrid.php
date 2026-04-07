<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trait SupportsAgGrid
 *
 * Adds server-side row model support for AG Grid.
 *
 * AG Grid sends these params when using the Server-Side Row Model:
 *   startRow, endRow, sortModel[], filterModel{}
 *
 * Controllers opt in by calling $this->applyAgGrid($query, $request)
 * when the request contains 'startRow'. All existing callers that use
 * standard pagination remain unaffected.
 *
 * Usage in a controller method:
 *   if ($this->isAgGridRequest($request)) {
 *       return $this->applyAgGrid(MyModel::forOrg($orgId), $request);
 *   }
 *   return $this->paginated(MyModel::forOrg($orgId)->paginate());
 */
trait SupportsAgGrid
{
    public function isAgGridRequest(Request $request): bool
    {
        return $request->has('startRow');
    }

    /**
     * Apply AG Grid server-side row model params to an Eloquent Builder and
     * return a JSON response in AG Grid's expected format:
     *   { rowCount: int, rows: array }
     *
     * Sort model items: [{ colId: string, sort: 'asc'|'desc' }]
     *
     * Filter model items:
     *   { colId: { filterType: 'text'|'number'|'date', type: string, filter: mixed } }
     */
    public function applyAgGrid(Builder $query, Request $request): JsonResponse
    {
        $startRow = max(0, (int) $request->input('startRow', 0));
        $endRow   = max($startRow + 1, (int) $request->input('endRow', 100));
        $limit    = min($endRow - $startRow, 500); // safety cap — never return > 500 rows

        // Apply sort model
        $sortModel = $request->input('sortModel', []);
        foreach ($sortModel as $sort) {
            $colId     = $this->sanitizeColumnName((string) ($sort['colId'] ?? ''));
            $direction = strtolower((string) ($sort['sort'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

            if ($colId !== '') {
                $query->orderBy($colId, $direction);
            }
        }

        // Apply filter model
        $filterModel = $request->input('filterModel', []);
        foreach ($filterModel as $colId => $filter) {
            $colId = $this->sanitizeColumnName((string) $colId);

            if ($colId === '' || ! is_array($filter)) {
                continue;
            }

            $this->applyAgGridFilter($query, $colId, $filter);
        }

        // Count before pagination (uses a separate count query for performance)
        $rowCount = $query->toBase()->getCountForPagination();

        $rows = $query->skip($startRow)->take($limit)->get();

        return response()->json([
            'success'  => true,
            'rowCount' => $rowCount,
            'rows'     => $rows,
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Apply a single AG Grid filter condition to the query.
     *
     * Supported filterType values: text, number, date
     * Supported condition types: equals, notEqual, contains, startsWith, endsWith,
     *   lessThan, lessThanOrEqual, greaterThan, greaterThanOrEqual, inRange
     */
    private function applyAgGridFilter(Builder $query, string $colId, array $filter): void
    {
        $filterType = strtolower((string) ($filter['filterType'] ?? 'text'));
        $type       = strtolower((string) ($filter['type'] ?? 'equals'));
        $value      = $filter['filter'] ?? null;

        // Combined conditions (AG Grid sends 'AND'/'OR' operator with filter1/filter2)
        if (isset($filter['operator'], $filter['condition1'], $filter['condition2'])) {
            $operator = strtoupper((string) $filter['operator']) === 'OR' ? 'orWhere' : 'where';

            $query->where(function (Builder $q) use ($colId, $filter, $operator) {
                $q->where(function (Builder $inner) use ($colId, $filter) {
                    $this->applyAgGridFilter($inner, $colId, $filter['condition1']);
                });
                $q->{$operator}(function (Builder $inner) use ($colId, $filter) {
                    $this->applyAgGridFilter($inner, $colId, $filter['condition2']);
                });
            });

            return;
        }

        match ($filterType) {
            'text'   => $this->applyTextFilter($query, $colId, $type, (string) $value),
            'number' => $this->applyNumberFilter($query, $colId, $type, $value, $filter),
            'date'   => $this->applyDateFilter($query, $colId, $type, $value, $filter),
            default  => null,
        };
    }

    private function applyTextFilter(Builder $query, string $colId, string $type, string $value): void
    {
        match ($type) {
            'equals'      => $query->where($colId, $value),
            'notEqual'    => $query->where($colId, '!=', $value),
            'contains'    => $query->where($colId, 'like', '%' . addcslashes($value, '%_') . '%'),
            'notContains' => $query->where($colId, 'not like', '%' . addcslashes($value, '%_') . '%'),
            'startsWith'  => $query->where($colId, 'like', addcslashes($value, '%_') . '%'),
            'endsWith'    => $query->where($colId, 'like', '%' . addcslashes($value, '%_')),
            'blank'       => $query->whereNull($colId)->orWhere($colId, ''),
            'notBlank'    => $query->whereNotNull($colId)->where($colId, '!=', ''),
            default       => null,
        };
    }

    private function applyNumberFilter(Builder $query, string $colId, string $type, mixed $value, array $filter): void
    {
        $to = $filter['filterTo'] ?? null;

        match ($type) {
            'equals'           => $query->where($colId, (float) $value),
            'notEqual'         => $query->where($colId, '!=', (float) $value),
            'lessThan'         => $query->where($colId, '<', (float) $value),
            'lessThanOrEqual'  => $query->where($colId, '<=', (float) $value),
            'greaterThan'      => $query->where($colId, '>', (float) $value),
            'greaterThanOrEqual' => $query->where($colId, '>=', (float) $value),
            'inRange'          => $query->whereBetween($colId, [(float) $value, (float) $to]),
            'blank'            => $query->whereNull($colId),
            'notBlank'         => $query->whereNotNull($colId),
            default            => null,
        };
    }

    private function applyDateFilter(Builder $query, string $colId, string $type, mixed $value, array $filter): void
    {
        $to = $filter['dateTo'] ?? null;

        match ($type) {
            'equals'           => $query->whereDate($colId, (string) $value),
            'notEqual'         => $query->whereDate($colId, '!=', (string) $value),
            'lessThan'         => $query->whereDate($colId, '<', (string) $value),
            'greaterThan'      => $query->whereDate($colId, '>', (string) $value),
            'inRange'          => $query->whereBetween($colId, [(string) $value, (string) $to]),
            'blank'            => $query->whereNull($colId),
            'notBlank'         => $query->whereNotNull($colId),
            default            => null,
        };
    }

    /**
     * Allow only alphanumeric characters, underscores, and dots (for joined tables).
     * Prevents SQL injection via column name injection.
     */
    private function sanitizeColumnName(string $colId): string
    {
        return preg_replace('/[^a-zA-Z0-9_.]/u', '', $colId) ?? '';
    }
}
