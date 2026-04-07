<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubcontractTransfer extends Model
{
    use HasFactory, HasUuid;

    public const TYPE_OUTWARD = 'outward';
    public const TYPE_INWARD  = 'inward';

    protected $guarded = ['id'];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    // Relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(SubcontractOrder::class, 'order_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SubcontractTransferLine::class, 'transfer_id');
    }

    // Helpers

    public function isOutward(): bool
    {
        return $this->transfer_type === self::TYPE_OUTWARD;
    }

    public function isInward(): bool
    {
        return $this->transfer_type === self::TYPE_INWARD;
    }
}
