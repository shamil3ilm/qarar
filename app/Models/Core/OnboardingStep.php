<?php

declare(strict_types=1);

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingStep extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_required' => 'boolean',
        'order'       => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'template_id');
    }

    public function progressRecords(): HasMany
    {
        return $this->hasMany(UserOnboardingProgress::class, 'step_id');
    }
}
