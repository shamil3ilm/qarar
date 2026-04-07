<?php

declare(strict_types=1);

namespace App\Models\Campaign;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserSegment extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'conditions',
        'color',
        'is_dynamic',
        'member_count',
        'last_evaluated_at',
        'created_by',
    ];

    protected $casts = [
        'conditions'        => 'array',
        'is_dynamic'        => 'bool',
        'last_evaluated_at' => 'datetime',
        'member_count'      => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_segment_memberships', 'segment_id', 'user_id')
            ->withPivot('assigned_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_dynamic', true);
    }
}
