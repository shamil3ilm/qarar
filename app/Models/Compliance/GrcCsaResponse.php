<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrcCsaResponse extends Model
{
    use HasFactory;

    protected $table = 'grc_csa_responses';

    protected $fillable = [
        'questionnaire_id',
        'question_id',
        'respondent_id',
        'response_value',
        'comments',
        'is_effective',
        'reviewer_notes',
    ];

    protected function casts(): array
    {
        return [
            'is_effective' => 'boolean',
        ];
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(GrcCsaQuestionnaire::class, 'questionnaire_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(GrcCsaQuestion::class, 'question_id');
    }

    public function respondent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondent_id');
    }
}
