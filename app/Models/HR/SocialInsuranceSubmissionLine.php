<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialInsuranceSubmissionLine extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'social_insurance_submission_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'insurable_salary' => 'decimal:4',
            'employee_contribution' => 'decimal:4',
            'employer_contribution' => 'decimal:4',
            'work_hazard_contribution' => 'decimal:4',
            'total_contribution' => 'decimal:4',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(SocialInsuranceSubmission::class, 'submission_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(SocialInsuranceRecord::class, 'record_id');
    }
}
