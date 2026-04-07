<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrcCsaQuestion extends Model
{
    use HasFactory;

    protected $table = 'grc_csa_questions';

    public const RESPONSE_YES_NO      = 'yes_no';
    public const RESPONSE_RATING_1_5  = 'rating_1_5';
    public const RESPONSE_TEXT        = 'text';
    public const RESPONSE_DATE        = 'date';
    public const RESPONSE_PERCENTAGE  = 'percentage';

    protected $fillable = [
        'questionnaire_id',
        'sort_order',
        'question_text',
        'guidance',
        'response_type',
        'is_required',
        'control_objective',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'sort_order'  => 'integer',
        ];
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(GrcCsaQuestionnaire::class, 'questionnaire_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(GrcCsaResponse::class, 'question_id');
    }
}
