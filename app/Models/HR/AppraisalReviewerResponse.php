<?php

declare(strict_types=1);

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalReviewerResponse extends Model
{
    protected $table = 'appraisal_reviewer_responses';

    protected $fillable = [
        'appraisal_reviewer_id',
        'question_id',
        'question_text',
        'rating',
        'response_text',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function appraisalReviewer(): BelongsTo
    {
        return $this->belongsTo(AppraisalReviewer::class, 'appraisal_reviewer_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AppraisalTemplateQuestion::class, 'question_id');
    }
}
