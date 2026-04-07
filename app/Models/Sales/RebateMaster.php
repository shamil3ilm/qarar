<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\Account;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RebateMaster extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED_AMOUNT = 'fixed_amount';
    public const TYPE_TIERED = 'tiered';

    public const BASE_INVOICE_VALUE = 'invoice_value';
    public const BASE_QUANTITY = 'quantity';
    public const BASE_GROSS_PROFIT = 'gross_profit';

    public const METHOD_PERIODIC = 'periodic';
    public const METHOD_ON_INVOICE = 'on_invoice';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'contact_id',
        'rebate_type',
        'calculation_base',
        'rebate_rate',
        'accrual_method',
        'valid_from',
        'valid_to',
        'minimum_purchase',
        'maximum_rebate',
        'accrual_account_id',
        'expense_account_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'rebate_rate'      => 'decimal:4',
            'minimum_purchase' => 'decimal:4',
            'maximum_rebate'   => 'decimal:4',
            'valid_from'       => 'date',
            'valid_to'         => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function accrualAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accrual_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(RebateAccrual::class, 'rebate_master_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isValidOn(\DateTimeInterface $date): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $checkDate = \Carbon\Carbon::instance($date);

        if ($checkDate->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_to && $checkDate->gt($this->valid_to)) {
            return false;
        }

        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeValidOn($query, \DateTimeInterface $date)
    {
        return $query->active()
            ->where('valid_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            });
    }

    public function scopeForContact($query, int $contactId)
    {
        return $query->where('contact_id', $contactId);
    }
}
