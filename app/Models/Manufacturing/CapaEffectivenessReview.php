<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapaEffectivenessReview extends Model
{
    use HasUuid;

    protected $table = 'capa_effectiveness_reviews';

    protected $fillable = [
        'uuid', 'capa_record_id', 'review_date', 'reviewed_by_id',
        'effectiveness', 'evidence', 'conclusions',
    ];

    protected $casts = ['review_date' => 'date'];

    public function capaRecord(): BelongsTo
    {
        return $this->belongsTo(CapaRecord::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
