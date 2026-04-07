<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OmTask extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const TYPE_FUNCTION       = 'function';
    public const TYPE_ACTIVITY       = 'activity';
    public const TYPE_RESPONSIBILITY = 'responsibility';

    protected $fillable = [
        'organization_id',
        'task_code',
        'name',
        'description',
        'task_type',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function positionAssignments(): HasMany
    {
        return $this->hasMany(OmPositionTask::class, 'task_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
