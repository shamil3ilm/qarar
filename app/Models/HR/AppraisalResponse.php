<?php

declare(strict_types=1);

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalResponse extends Model
{
    public const RESPONDENT_SELF = 'self';
    public const RESPONDENT_MANAGER = 'manager';

    protected $fillable = [
        'performance_appraisal_id',
        'appraisal_template_question_id',
        'respondent_type',
        'rating',
        'text_response',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    // ---------------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------------

    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(PerformanceAppraisal::class, 'performance_appraisal_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AppraisalTemplateQuestion::class, 'appraisal_template_question_id');
    }
}
