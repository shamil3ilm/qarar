<?php

declare(strict_types=1);

namespace App\Rules\Tax;

use App\Contracts\VersionedRule;
use Carbon\Carbon;

/** GCC VAT — 15% standard rate, effective 2020-07-01 */
final class VatRuleV1 implements VersionedRule
{
    public const STANDARD_RATE = 0.15;
    public const REDUCED_RATE  = 0.00; // zero-rated

    public function effectiveFrom(): \DateTimeInterface
    {
        return Carbon::parse('2020-07-01');
    }

    public function version(): string
    {
        return 'v1';
    }

    public function calculate(float $amount, string $category = 'standard'): array
    {
        $rate = match ($category) {
            'zero_rated', 'exempt' => self::REDUCED_RATE,
            default                => self::STANDARD_RATE,
        };

        $tax = round($amount * $rate, 4);

        return [
            'base_amount'  => $amount,
            'tax_rate'     => $rate,
            'tax_amount'   => $tax,
            'total'        => $amount + $tax,
            'rule_version' => $this->version(),
        ];
    }
}
