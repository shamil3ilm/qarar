<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_FROZEN    = 'frozen';
    public const STATUS_ABOLISHED = 'abolished';

    protected $fillable = [
        'organization_id',
        'position_code',
        'position_title',
        'department_id',
        'designation_id',
        'pay_grade_id',
        'reports_to_position_id',
        'headcount_authorized',
        'headcount_filled',
        'is_key_position',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'headcount_authorized' => 'integer',
            'headcount_filled'     => 'integer',
            'is_key_position'      => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function payGrade(): BelongsTo
    {
        return $this->belongsTo(PayGrade::class);
    }

    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reports_to_position_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'reports_to_position_id');
    }

    public function getHeadcountVacant(): int
    {
        return max(0, $this->headcount_authorized - $this->headcount_filled);
    }

    public function hasVacancy(): bool
    {
        return $this->getHeadcountVacant() > 0;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeKeyPositions($query)
    {
        return $query->where('is_key_position', true);
    }

    public function scopeWithVacancy($query)
    {
        return $query->whereRaw('headcount_filled < headcount_authorized');
    }

    public function scopeInDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('reports_to_position_id');
    }
}
