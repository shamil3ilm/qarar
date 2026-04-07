<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowSubstitutionRule extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to'   => 'date',
            'is_active'  => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForApprover(Builder $query, int $approverId): Builder
    {
        return $query->where('approver_id', $approverId);
    }

    public function scopeValidNow(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('valid_from', '<=', $today)
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
            });
    }
}
