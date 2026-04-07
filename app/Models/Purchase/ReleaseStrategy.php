<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReleaseStrategy extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const DOCUMENT_TYPE_PURCHASE_ORDER = 'purchase_order';
    public const DOCUMENT_TYPE_PURCHASE_REQUISITION = 'purchase_requisition';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'document_type',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function levels(): HasMany
    {
        return $this->hasMany(ReleaseStrategyLevel::class)->orderBy('level');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ReleaseStrategyApproval::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDocument(Builder $query, string $type): Builder
    {
        return $query->where('document_type', $type);
    }
}
