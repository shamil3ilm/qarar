<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class PaymentTerm extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'net_days',
        'discount_days',
        'discount_pct',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'net_days'      => 'integer',
            'discount_days' => 'integer',
            'discount_pct'  => 'decimal:2',
            'is_active'     => 'boolean',
        ];
    }

    /**
     * Determine whether the payment date falls within the early-payment discount window.
     */
    public function isEligibleForDiscount(
        DateTimeInterface $paymentDate,
        DateTimeInterface $invoiceDate
    ): bool {
        if ($this->discount_days === 0 || bccomp((string) $this->discount_pct, '0', 2) <= 0) {
            return false;
        }

        $cutoff = (clone $invoiceDate)->modify("+{$this->discount_days} days");

        return $paymentDate <= $cutoff;
    }

    /**
     * Calculate the discount amount for the given outstanding balance.
     */
    public function calculateDiscount(string $amount): string
    {
        return bcmul(
            $amount,
            bcdiv((string) $this->discount_pct, '100', 6),
            4
        );
    }
}
