<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    // Source constants
    public const SOURCE_JOB_BOARD = 'job_board';
    public const SOURCE_REFERRAL  = 'referral';
    public const SOURCE_LINKEDIN  = 'linkedin';
    public const SOURCE_DIRECT    = 'direct';
    public const SOURCE_AGENCY    = 'agency';
    public const SOURCE_OTHER     = 'other';

    protected $fillable = [
        'organization_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'linkedin_url',
        'resume_path',
        'total_experience_years',
        'current_company',
        'current_title',
        'source',
        'notes',
    ];

    protected $casts = [
        'total_experience_years' => 'decimal:1',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    // -------------------------------------------------------------------------
    // Business logic helpers
    // -------------------------------------------------------------------------

    public function getDisplayName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
