<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobPosting extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasFactory, HasUuid, SoftDeletes;

    // Employment type constants
    public const EMPLOYMENT_TYPE_FULL_TIME = 'full_time';
    public const EMPLOYMENT_TYPE_PART_TIME = 'part_time';
    public const EMPLOYMENT_TYPE_CONTRACT  = 'contract';
    public const EMPLOYMENT_TYPE_INTERN    = 'intern';

    // Status constants
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_OPEN      = 'open';
    public const STATUS_ON_HOLD   = 'on_hold';
    public const STATUS_CLOSED    = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'department_id',
        'designation_id',
        'title',
        'description',
        'requirements',
        'employment_type',
        'location',
        'salary_min',
        'salary_max',
        'currency_code',
        'vacancies',
        'filled_count',
        'status',
        'posted_at',
        'closes_at',
        'created_by',
    ];

    protected $casts = [
        'salary_min'  => 'decimal:2',
        'salary_max'  => 'decimal:2',
        'vacancies'   => 'integer',
        'filled_count' => 'integer',
        'posted_at'   => 'datetime',
        'closes_at'   => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Business logic helpers
    // -------------------------------------------------------------------------

    public function isOpen(): bool
    {
        if ($this->status !== self::STATUS_OPEN) {
            return false;
        }

        if ($this->closes_at !== null && $this->closes_at->isPast()) {
            return false;
        }

        return $this->remainingVacancies() > 0;
    }

    public function remainingVacancies(): int
    {
        return max(0, $this->vacancies - $this->filled_count);
    }
}
