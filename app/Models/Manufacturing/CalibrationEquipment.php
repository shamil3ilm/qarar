<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalibrationEquipment extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const CATEGORY_THERMOMETER    = 'thermometer';
    public const CATEGORY_PRESSURE_GAUGE = 'pressure_gauge';
    public const CATEGORY_SCALE          = 'scale';
    public const CATEGORY_CALIPER        = 'caliper';
    public const CATEGORY_MULTIMETER     = 'multimeter';
    public const CATEGORY_OTHER          = 'other';

    protected $fillable = [
        'organization_id',
        'equipment_code',
        'name',
        'manufacturer',
        'model_number',
        'serial_number',
        'category',
        'location',
        'responsible_person_id',
        'purchase_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'is_active'     => 'boolean',
        ];
    }

    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_person_id');
    }

    public function calibrationPlans(): HasMany
    {
        return $this->hasMany(CalibrationPlan::class);
    }

    public function calibrationOrders(): HasMany
    {
        return $this->hasMany(CalibrationOrder::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getLatestOrder(): ?CalibrationOrder
    {
        return $this->calibrationOrders()
            ->whereIn('status', ['completed'])
            ->orderByDesc('completed_date')
            ->first();
    }

    public function getNextScheduledOrder(): ?CalibrationOrder
    {
        return $this->calibrationOrders()
            ->whereIn('status', ['planned', 'in_progress'])
            ->orderBy('scheduled_date')
            ->first();
    }
}
