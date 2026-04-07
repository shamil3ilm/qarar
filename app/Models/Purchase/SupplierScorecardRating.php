<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierScorecardRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'scorecard_id',
        'criterion_id',
        'score',
        'comments',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    // Relationships

    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(SupplierScorecard::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(SupplierEvaluationCriteria::class, 'criterion_id');
    }
}
