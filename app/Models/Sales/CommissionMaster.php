<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionMaster extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'organization_id',
        'sales_rep_id',
        'commission_plan_name',
        'effective_from',
        'effective_to',
        'base_rate',
        'currency',
        'quota_amount',
        'status',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'base_rate'      => 'decimal:4',
        'quota_amount'   => 'decimal:4',
    ];

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CommissionRule::class);
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(CommissionCalculation::class);
    }
}
