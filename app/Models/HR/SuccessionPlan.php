<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuccessionPlan extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'succession_plans';

    protected $guarded = ['id'];

    public const CRITICALITY_CRITICAL = 'critical';
    public const CRITICALITY_HIGH = 'high';
    public const CRITICALITY_MEDIUM = 'medium';
    public const CRITICALITY_LOW = 'low';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_COMPLETED = 'completed';

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
        ];
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(SuccessionPlanCandidate::class, 'succession_plan_id');
    }

    public function currentEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'current_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByCriticality($query, string $criticality)
    {
        return $query->where('criticality', $criticality);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
