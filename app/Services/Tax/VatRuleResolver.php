<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Contracts\VersionedRule;
use App\Rules\Tax\VatRuleV1;
use Carbon\Carbon;

/**
 * Resolves the correct VAT rule version for a given transaction date.
 * Old invoices use the rule that was active when they were created.
 */
final class VatRuleResolver
{
    /** @var list<VersionedRule> Ordered oldest-first */
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            new VatRuleV1(),
            // Add new versions here as regulations change:
            // new VatRuleV2(),  // effective 2026-01-01
        ];
    }

    /**
     * Return the rule version that was active on the given date.
     * Falls back to the earliest rule if date predates all versions.
     */
    public function resolveForDate(\DateTimeInterface|string $date): VersionedRule
    {
        $transactionDate = Carbon::parse($date);

        // Walk rules newest-first; return first one whose effectiveFrom <= transaction date
        $sorted = array_reverse($this->rules);

        foreach ($sorted as $rule) {
            if ($transactionDate->gte(Carbon::instance($rule->effectiveFrom()))) {
                return $rule;
            }
        }

        // If date predates all rules, use the oldest
        return $this->rules[0];
    }

    /** Return all registered rule versions */
    public function allVersions(): array
    {
        return array_map(fn(VersionedRule $r) => [
            'version'        => $r->version(),
            'effective_from' => Carbon::instance($r->effectiveFrom())->toDateString(),
        ], $this->rules);
    }
}
