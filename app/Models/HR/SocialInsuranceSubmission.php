<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialInsuranceSubmission extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'social_insurance_submissions';

    protected $guarded = ['id'];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'total_employees' => 'integer',
            'total_insurable_salary' => 'decimal:4',
            'total_employee_contrib' => 'decimal:4',
            'total_employer_contrib' => 'decimal:4',
            'total_work_hazard_contrib' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'submitted_at' => 'datetime',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(SocialInsuranceScheme::class, 'scheme_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SocialInsuranceSubmissionLine::class, 'submission_id');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
