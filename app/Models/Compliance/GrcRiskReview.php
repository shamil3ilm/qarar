<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrcRiskReview extends Model
{
    use HasFactory;

    protected $table = 'grc_risk_reviews';

    protected $fillable = [
        'risk_id',
        'review_date',
        'reviewed_likelihood',
        'reviewed_impact',
        'notes',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'review_date'         => 'date',
            'reviewed_likelihood' => 'integer',
            'reviewed_impact'     => 'integer',
        ];
    }

    public function risk(): BelongsTo
    {
        return $this->belongsTo(GrcRisk::class, 'risk_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
