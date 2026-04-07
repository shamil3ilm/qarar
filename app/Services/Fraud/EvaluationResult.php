<?php

declare(strict_types=1);

namespace App\Services\Fraud;

readonly class EvaluationResult
{
    /**
     * @param array<int, array{rule_id: int, rule_name: string, score: int}> $triggeredRules
     */
    public function __construct(
        public readonly bool   $flagged,
        public readonly int    $totalScore,
        public readonly array  $triggeredRules,
        public readonly string $highestSeverity,
        public readonly bool   $shouldBlock,
    ) {}

    public static function noFlag(): self
    {
        return new self(
            flagged:         false,
            totalScore:      0,
            triggeredRules:  [],
            highestSeverity: 'low',
            shouldBlock:     false,
        );
    }
}
