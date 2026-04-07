<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaseContract extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 're_contracts';

    protected $fillable = [
        'organization_id',
        'contract_number',
        'contract_type',
        'rental_unit_id',
        'counterparty_type',
        'counterparty_id',
        'counterparty_name',
        'start_date',
        'end_date',
        'notice_date',
        'notice_period_months',
        'status',
        'currency_code',
        'payment_day',
        'payment_frequency',
        'auto_renew',
        'auto_renew_months',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'notice_date' => 'date',
        'notice_period_months' => 'integer',
        'payment_day' => 'integer',
        'auto_renew' => 'boolean',
        'auto_renew_months' => 'integer',
    ];

    public function rentalUnit(): BelongsTo
    {
        return $this->belongsTo(RentalUnit::class, 'rental_unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(ContractCondition::class, 'contract_id');
    }

    public function activeConditions(): HasMany
    {
        return $this->hasMany(ContractCondition::class, 'contract_id')
            ->where('is_active', true)
            ->where('valid_from', '<=', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString()));
    }

    public function options(): HasMany
    {
        return $this->hasMany(ContractOption::class, 'contract_id');
    }

    public function securityDeposit(): HasOne
    {
        return $this->hasOne(SecurityDeposit::class, 'contract_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isLeaseOut(): bool
    {
        return $this->contract_type === 'lease_out';
    }
}
