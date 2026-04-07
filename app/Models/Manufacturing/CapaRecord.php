<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CapaRecord extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'capa_records';

    protected $fillable = [
        'uuid', 'organization_id', 'capa_number', 'capa_type',
        'source_type', 'source_id', 'problem_statement', 'root_cause',
        'priority', 'status', 'owner_id', 'target_close_date', 'actual_close_date',
    ];

    protected $casts = [
        'target_close_date' => 'date',
        'actual_close_date' => 'date',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CapaAction::class);
    }

    public function effectivenessReviews(): HasMany
    {
        return $this->hasMany(CapaEffectivenessReview::class);
    }
}
