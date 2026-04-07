<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DunningLevel extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'level_number'    => 'integer',
            'days_overdue_from' => 'integer',
            'days_overdue_to'   => 'integer',
            'interest_rate'   => 'decimal:2',
            'dunning_fee'     => 'decimal:4',
            'is_legal_action' => 'boolean',
            'is_active'       => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function notices(): HasMany
    {
        return $this->hasMany(DunningNotice::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Find the applicable dunning level for the given days overdue.
     */
    public static function forDaysOverdue(int $organizationId, int $daysOverdue): ?self
    {
        return static::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where('days_overdue_from', '<=', $daysOverdue)
            ->where(function ($q) use ($daysOverdue) {
                $q->whereNull('days_overdue_to')
                    ->orWhere('days_overdue_to', '>=', $daysOverdue);
            })
            ->orderByDesc('level_number')
            ->first();
    }
}
