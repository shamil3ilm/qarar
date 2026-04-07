<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaDeliverySchedule extends Model
{
    use BelongsToOrganization, HasUuid;

    public const STATUS_OPEN      = 'open';
    public const STATUS_PARTIAL   = 'partial';
    public const STATUS_COMPLETE  = 'complete';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'scheduling_agreement_id',
        'schedule_date',
        'scheduled_quantity',
        'received_quantity',
        'status',
        'goods_receipt_id',
    ];

    protected function casts(): array
    {
        return [
            'schedule_date'      => 'date',
            'scheduled_quantity' => 'decimal:4',
            'received_quantity'  => 'decimal:4',
        ];
    }

    // Relationships

    public function schedulingAgreement(): BelongsTo
    {
        return $this->belongsTo(SchedulingAgreement::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    // Scopes

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', self::STATUS_OPEN)
            ->where('schedule_date', '>=', today());
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OPEN)
            ->where('schedule_date', '<', today());
    }
}
