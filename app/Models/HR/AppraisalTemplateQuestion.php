<?php

declare(strict_types=1);

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppraisalTemplateQuestion extends Model
{
    public const TYPE_RATING = 'rating';
    public const TYPE_TEXT = 'text';
    public const TYPE_YES_NO = 'yes_no';
    public const TYPE_MULTISELECT = 'multiselect';

    public const TYPES = [
        self::TYPE_RATING,
        self::TYPE_TEXT,
        self::TYPE_YES_NO,
        self::TYPE_MULTISELECT,
    ];

    protected $fillable = [
        'appraisal_template_section_id',
        'question',
        'question_type',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ---------------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------------

    public function section(): BelongsTo
    {
        return $this->belongsTo(AppraisalTemplateSection::class, 'appraisal_template_section_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AppraisalResponse::class, 'appraisal_template_question_id');
    }
}
