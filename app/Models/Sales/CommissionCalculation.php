<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionCalculation extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid;

    public const STATUS_CALCULATED = 'calculated';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_PAID       = 'paid';
    public const STATUS_REVERSED   = 'reversed';

    protected $fillable = [
        'organization_id',
        'commission_master_id',
        'invoice_id',
        'sales_order_id',
        'period_year',
        'period_month',
        'base_amount',
        'commission_rate',
        'commission_amount',
        'currency',
        'status',
        'calculated_at',
        'approved_by',
        'paid_at',
    ];

    protected $casts = [
        'base_amount'      => 'decimal:4',
        'commission_rate'  => 'decimal:4',
        'commission_amount'=> 'decimal:4',
        'calculated_at'    => 'datetime',
        'paid_at'          => 'datetime',
    ];

    public function commissionMaster(): BelongsTo
    {
        return $this->belongsTo(CommissionMaster::class, 'commission_master_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
