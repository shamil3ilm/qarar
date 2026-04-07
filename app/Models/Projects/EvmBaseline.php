<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvmBaseline extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'project_evm_baselines';

    protected $guarded = ['id'];

    public const TYPE_ORIGINAL = 'original';
    public const TYPE_REVISED  = 'revised';
    public const TYPE_CURRENT  = 'current';

    protected function casts(): array
    {
        return [
            'baseline_date'         => 'date',
            'planned_start'         => 'date',
            'planned_finish'        => 'date',
            'planned_cost'          => 'decimal:2',
            'planned_duration_days' => 'decimal:1',
            'is_active'             => 'boolean',
            'approved_at'           => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EvmBaselineLine::class, 'baseline_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
