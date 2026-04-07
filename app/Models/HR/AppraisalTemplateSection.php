<?php

declare(strict_types=1);

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppraisalTemplateSection extends Model
{
    protected $fillable = [
        'appraisal_template_id',
        'name',
        'description',
        'weight_percent',
        'sort_order',
    ];

    protected $casts = [
        'weight_percent' => 'float',
        'sort_order' => 'integer',
    ];

    // ---------------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------------

    public function template(): BelongsTo
    {
        return $this->belongsTo(AppraisalTemplate::class, 'appraisal_template_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AppraisalTemplateQuestion::class)->orderBy('sort_order');
    }
}
