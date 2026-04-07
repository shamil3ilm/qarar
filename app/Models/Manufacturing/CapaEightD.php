<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CapaEightD extends Model
{
    use HasUuid;
    use SoftDeletes;
    use HasAuditTrail;

    protected $table = 'qm_capa_8d';

    // Status constants — one per discipline
    public const STATUS_D0_OPEN         = 'd0_open';
    public const STATUS_D1_TEAM         = 'd1_team';
    public const STATUS_D2_PROBLEM      = 'd2_problem';
    public const STATUS_D3_CONTAINMENT  = 'd3_containment';
    public const STATUS_D4_ROOT_CAUSE   = 'd4_root_cause';
    public const STATUS_D5_ACTIONS      = 'd5_actions';
    public const STATUS_D6_IMPLEMENTED  = 'd6_implemented';
    public const STATUS_D7_PREVENTION   = 'd7_prevention';
    public const STATUS_D8_CLOSED       = 'd8_closed';

    /** Ordered list of steps used for progression validation. */
    public const STEP_ORDER = [
        self::STATUS_D0_OPEN,
        self::STATUS_D1_TEAM,
        self::STATUS_D2_PROBLEM,
        self::STATUS_D3_CONTAINMENT,
        self::STATUS_D4_ROOT_CAUSE,
        self::STATUS_D5_ACTIONS,
        self::STATUS_D6_IMPLEMENTED,
        self::STATUS_D7_PREVENTION,
        self::STATUS_D8_CLOSED,
    ];

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'capa_number',
        'title',
        'status',
        'source_complaint_id',
        'source_type',
        'created_by',
        // D0
        'd0_emergency_response',
        'd0_date',
        // D1
        'd1_team_members',
        'd1_champion_id',
        // D2
        'd2_problem_description',
        'd2_is_is_not',
        // D3
        'd3_containment_actions',
        'd3_implemented_date',
        'd3_verified',
        // D4
        'd4_root_cause',
        'd4_escape_point',
        // D5
        'd5_corrective_actions',
        // D6
        'd6_implementation_plan',
        'd6_target_date',
        'd6_completed_date',
        'd6_verified',
        // D7
        'd7_systemic_preventions',
        'd7_lessons_learned',
        // D8
        'd8_recognition',
        'd8_closure_date',
    ];

    protected function casts(): array
    {
        return [
            'd1_team_members'      => 'array',
            'd3_verified'          => 'boolean',
            'd6_verified'          => 'boolean',
            'd0_date'              => 'date',
            'd3_implemented_date'  => 'date',
            'd6_target_date'       => 'date',
            'd6_completed_date'    => 'date',
            'd8_closure_date'      => 'date',
        ];
    }

    public function champion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'd1_champion_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen($query): mixed
    {
        return $query->whereNotIn('status', [self::STATUS_D8_CLOSED]);
    }
}
