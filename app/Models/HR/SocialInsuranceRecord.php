<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialInsuranceRecord extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'social_insurance_records';

    protected $guarded = ['id'];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_TERMINATED = 'terminated';

    protected function casts(): array
    {
        return [
            'enrollment_date' => 'date',
            'termination_date' => 'date',
            'insurable_salary' => 'decimal:4',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(SocialInsuranceScheme::class, 'scheme_id');
    }

    public function submissionLines(): HasMany
    {
        return $this->hasMany(SocialInsuranceSubmissionLine::class, 'record_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
