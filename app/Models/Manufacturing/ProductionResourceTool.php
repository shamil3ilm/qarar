<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionResourceTool extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_IN_USE = 'in_use';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'organization_id',
        'prt_number',
        'prt_name',
        'prt_type',
        'status',
        'location',
        'quantity_available',
        'quantity_in_use',
        'serial_number',
        'next_calibration_date',
        'notes',
    ];

    protected $casts = [
        'quantity_available' => 'integer',
        'quantity_in_use' => 'integer',
        'next_calibration_date' => 'date',
    ];

    // Relationships

    public function assignments(): HasMany
    {
        return $this->hasMany(PrtOperationAssignment::class);
    }

    // Scopes

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('prt_type', $type);
    }

    // Helpers

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE
            && ($this->quantity_available - $this->quantity_in_use) > 0;
    }

    public function assignTo(int $workOrderId, int $quantity = 1): void
    {
        $this->increment('quantity_in_use', $quantity);

        $availableAfter = $this->quantity_available - $this->quantity_in_use;

        if ($availableAfter <= 0) {
            $this->update(['status' => self::STATUS_IN_USE]);
        }
    }

    public function release(int $quantity = 1): void
    {
        $newInUse = max(0, $this->quantity_in_use - $quantity);
        $this->update([
            'quantity_in_use' => $newInUse,
            'status' => $newInUse === 0 ? self::STATUS_AVAILABLE : self::STATUS_IN_USE,
        ]);
    }
}
