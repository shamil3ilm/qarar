<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\ApiException;
use App\Models\Accounting\DocumentSplittingRule;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntrySplitItem;
use App\Models\Accounting\PostingValidationRule;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentSplittingService
{
    /**
     * Persist split items for a journal entry using the active splitting rules.
     *
     * Process:
     *  1. Load active splitting rules for the organisation, ordered by priority.
     *  2. For each rule, identify "base" lines (lines whose category matches
     *     the rule's base_item_category) and build a profit-centre weight map.
     *  3. Proportionally split the remaining lines into JournalEntrySplitItem
     *     records that reflect the profit-centre breakdown of the base items.
     *
     * @throws InvalidArgumentException When the journal entry has no lines loaded.
     */
    public function splitDocument(JournalEntry $entry): void
    {
        $lines = $entry->relationLoaded('lines') ? $entry->lines : $entry->lines()->get();

        if ($lines->isEmpty()) {
            return;
        }

        $rules = DocumentSplittingRule::where('organization_id', $entry->organization_id)
            ->active()
            ->ordered()
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($entry, $lines, $rules): void {
            // Remove any previously generated split items for this entry.
            JournalEntrySplitItem::where('journal_entry_id', $entry->id)->delete();

            foreach ($rules as $rule) {
                $this->applySplitRule($entry, $lines, $rule);
            }
        });
    }

    /**
     * Preview what the split would produce without persisting anything.
     *
     * @param  array<string, mixed>  $documentData  Simulated journal entry payload.
     * @return array<int, array<string, mixed>>
     */
    public function previewSplit(array $documentData): array
    {
        $orgId = (int) ($documentData['organization_id'] ?? 0);
        $lines = $documentData['lines'] ?? [];

        if (empty($lines) || $orgId === 0) {
            return [];
        }

        $rules = DocumentSplittingRule::where('organization_id', $orgId)
            ->active()
            ->ordered()
            ->get();

        if ($rules->isEmpty()) {
            return [];
        }

        $preview = [];

        foreach ($rules as $rule) {
            $baseLines  = $this->filterBaseLines($lines, $rule->base_item_category);
            $otherLines = $this->filterOtherLines($lines, $rule->base_item_category);
            $weights    = $this->buildWeightMap($baseLines, $rule->split_method);

            foreach ($otherLines as $line) {
                foreach ($weights as $centerId => $ratio) {
                    $debit  = round((float) ($line['debit'] ?? 0) * $ratio, 4);
                    $credit = round((float) ($line['credit'] ?? 0) * $ratio, 4);

                    if ($debit == 0 && $credit == 0) {
                        continue;
                    }

                    $splitItem = [
                        'original_line_id'    => $line['id'] ?? null,
                        'split_method'        => $rule->split_method,
                        'debit_amount'        => $debit,
                        'credit_amount'       => $credit,
                        'currency_code'       => $documentData['currency_code'] ?? 'SAR',
                        'ratio'               => $ratio,
                    ];

                    match ($rule->split_method) {
                        'profit_center' => $splitItem['profit_center_id'] = $centerId,
                        'segment'       => $splitItem['segment_id']       = $centerId,
                        default         => $splitItem['cost_center_id']   = $centerId,
                    };

                    $preview[] = $splitItem;
                }
            }
        }

        return $preview;
    }

    /**
     * Evaluate posting validation/substitution rules against document data.
     *
     * - Validation rules: throw ApiException if conditions match.
     * - Substitution rules: overwrite fields in $documentData when conditions match.
     *
     * @param  array<string, mixed>  $documentData
     * @return array<string, mixed>  Potentially modified document data.
     *
     * @throws ApiException When a validation rule is violated.
     */
    public function evaluatePostingRules(array $documentData, string $event = 'on_save'): array
    {
        $orgId = (int) ($documentData['organization_id'] ?? 0);

        $rules = PostingValidationRule::where('organization_id', $orgId)
            ->active()
            ->forEvent($event)
            ->ordered()
            ->get();

        foreach ($rules as $rule) {
            if (!$this->conditionsMatch($rule->conditions, $documentData)) {
                continue;
            }

            if ($rule->rule_type === 'validation') {
                throw new ApiException(
                    [
                        'code'        => 'POSTING_VALIDATION_FAILED',
                        'message'     => $rule->error_message ?? "Posting validation rule '{$rule->rule_name}' violated.",
                        'http_status' => 422,
                    ]
                );
            }

            // Substitution: apply field overwrites.
            $documentData = $this->applySubstitutionActions($rule->actions, $documentData);
        }

        return $documentData;
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function applySplitRule(JournalEntry $entry, \Illuminate\Support\Collection $lines, DocumentSplittingRule $rule): void
    {
        $baseLines  = $lines->filter(fn ($l) => $this->isBaseLine($l, $rule->base_item_category));
        $otherLines = $lines->filter(fn ($l) => !$this->isBaseLine($l, $rule->base_item_category));

        $weights = $this->buildWeightMapFromModels($baseLines, $rule->split_method);

        if (empty($weights)) {
            return;
        }

        foreach ($otherLines as $line) {
            foreach ($weights as $dimensionId => $ratio) {
                $debit  = round((float) $line->debit * $ratio, 4);
                $credit = round((float) $line->credit * $ratio, 4);

                if ($debit == 0 && $credit == 0) {
                    continue;
                }

                $splitData = [
                    'journal_entry_id' => $entry->id,
                    'original_line_id' => $line->id,
                    'split_method'     => $rule->split_method,
                    'debit_amount'     => $debit,
                    'credit_amount'    => $credit,
                    'currency_code'    => $entry->currency_code ?? 'SAR',
                ];

                $splitData = match ($rule->split_method) {
                    'profit_center' => array_merge($splitData, ['profit_center_id' => $dimensionId]),
                    'segment'       => array_merge($splitData, ['segment_id' => (string) $dimensionId]),
                    default         => array_merge($splitData, ['cost_center_id' => $dimensionId]),
                };

                JournalEntrySplitItem::create($splitData);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection  $baseLines
     * @return array<int|string, float>  centerId => ratio
     */
    private function buildWeightMapFromModels(\Illuminate\Support\Collection $baseLines, string $method): array
    {
        $totals = [];
        $grandTotal = 0.0;

        foreach ($baseLines as $line) {
            $dimensionId = match ($method) {
                'profit_center' => $line->profit_center_id,
                'segment'       => $line->segment_id,
                default         => $line->cost_center_id,
            };

            if ($dimensionId === null) {
                continue;
            }

            $amount = (float) $line->debit + (float) $line->credit;
            $totals[$dimensionId] = ($totals[$dimensionId] ?? 0.0) + $amount;
            $grandTotal += $amount;
        }

        if ($grandTotal == 0) {
            return [];
        }

        return array_map(fn (float $v): float => $v / $grandTotal, $totals);
    }

    /**
     * Build a weight map from raw line arrays (used in preview).
     *
     * @param  array<int, array<string, mixed>>  $baseLines
     * @return array<int|string, float>
     */
    private function buildWeightMap(array $baseLines, string $method): array
    {
        $totals     = [];
        $grandTotal = 0.0;

        foreach ($baseLines as $line) {
            $centerId = match ($method) {
                'profit_center' => $line['profit_center_id'] ?? null,
                'segment'       => $line['segment_id'] ?? null,
                default         => $line['cost_center_id'] ?? null,
            };

            if ($centerId === null) {
                continue;
            }

            $amount = (float) ($line['debit'] ?? 0) + (float) ($line['credit'] ?? 0);
            $totals[$centerId] = ($totals[$centerId] ?? 0.0) + $amount;
            $grandTotal += $amount;
        }

        if ($grandTotal == 0) {
            return [];
        }

        return array_map(fn (float $v): float => $v / $grandTotal, $totals);
    }

    private function isBaseLine(object $line, ?string $category): bool
    {
        if ($category === null) {
            return false;
        }

        return ($line->category ?? null) === $category
            || ($line->line_category ?? null) === $category;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function filterBaseLines(array $lines, ?string $category): array
    {
        if ($category === null) {
            return [];
        }

        return array_values(array_filter(
            $lines,
            fn (array $l) => ($l['category'] ?? $l['line_category'] ?? null) === $category
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function filterOtherLines(array $lines, ?string $category): array
    {
        if ($category === null) {
            return $lines;
        }

        return array_values(array_filter(
            $lines,
            fn (array $l) => ($l['category'] ?? $l['line_category'] ?? null) !== $category
        ));
    }

    /**
     * Evaluate whether all conditions in a rule's condition set match the document.
     *
     * Each condition: {field, operator, value}
     * Supported operators: eq, neq, gt, gte, lt, lte, in, not_in, is_null, is_not_null
     *
     * @param  array<int, array<string, mixed>>  $conditions
     * @param  array<string, mixed>              $data
     */
    private function conditionsMatch(array $conditions, array $data): bool
    {
        foreach ($conditions as $condition) {
            $field    = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'eq';
            $value    = $condition['value'] ?? null;
            $actual   = $data[$field] ?? null;

            $matches = match ($operator) {
                'eq'         => $actual == $value,
                'neq'        => $actual != $value,
                'gt'         => is_numeric($actual) && (float) $actual > (float) $value,
                'gte'        => is_numeric($actual) && (float) $actual >= (float) $value,
                'lt'         => is_numeric($actual) && (float) $actual < (float) $value,
                'lte'        => is_numeric($actual) && (float) $actual <= (float) $value,
                'in'         => is_array($value) && in_array($actual, $value, true),
                'not_in'     => is_array($value) && !in_array($actual, $value, true),
                'is_null'    => $actual === null,
                'is_not_null' => $actual !== null,
                default      => false,
            };

            if (!$matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply substitution actions to document data.
     *
     * Each action: {field, action_type, value}
     * action_type 'set' — overwrite the field with the given value.
     *
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<string, mixed>              $data
     * @return array<string, mixed>
     */
    private function applySubstitutionActions(array $actions, array $data): array
    {
        $result = $data;

        foreach ($actions as $action) {
            $field      = $action['field'] ?? '';
            $actionType = $action['action_type'] ?? 'set';
            $value      = $action['value'] ?? null;

            if ($field === '') {
                continue;
            }

            if ($actionType === 'set') {
                $result[$field] = $value;
            }
        }

        return $result;
    }
}
