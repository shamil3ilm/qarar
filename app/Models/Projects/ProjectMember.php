<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    use HasFactory;

    // ── Role constants ────────────────────────────────────────────────────────
    public const ROLE_MANAGER = 'manager';
    public const ROLE_MEMBER = 'member';
    public const ROLE_REVIEWER = 'reviewer';
    public const ROLE_SPONSOR = 'sponsor';

    protected $fillable = [
        'project_id',
        'employee_id',
        'role',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
