<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QmDynamicModificationRule extends Model
{
    use HasUuid;
    use SoftDeletes;
    use HasAuditTrail;

    protected $table = 'qm_dynamic_modification_rules';

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'rule_code',
        'name',
        'description',
        'tighten_consecutive_fails',
        'reduce_after_consecutive_pass',
        'skip_after_reduced_pass',
        'reinstate_after_tightened_fail',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active'                      => 'boolean',
            'tighten_consecutive_fails'       => 'integer',
            'reduce_after_consecutive_pass'   => 'integer',
            'skip_after_reduced_pass'         => 'integer',
            'reinstate_after_tightened_fail'  => 'integer',
        ];
    }

    public function stageLogs(): HasMany
    {
        return $this->hasMany(QmInspectionStageLog::class, 'rule_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }
}
