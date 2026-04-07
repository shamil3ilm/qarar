<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagerTeamView extends Model
{
    public const RELATIONSHIP_DIRECT = 'direct_report';
    public const RELATIONSHIP_INDIRECT = 'indirect_report';

    protected $fillable = [
        'organization_id',
        'manager_id',
        'employee_id',
        'relationship_type',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // Scopes

    public function scopeDirectReports(Builder $query): Builder
    {
        return $query->where('relationship_type', self::RELATIONSHIP_DIRECT);
    }

    public function scopeForManager(Builder $query, int $managerId): Builder
    {
        return $query->where('manager_id', $managerId);
    }
}
