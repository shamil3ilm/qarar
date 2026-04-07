<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOnboardingProgress extends Model
{
    use HasFactory;

    protected $table = 'user_onboarding_progress';

    protected $guarded = ['id'];

    protected $casts = [
        'completed_at' => 'datetime',
        'skipped_at'   => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'template_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(OnboardingStep::class, 'step_id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isSkipped(): bool
    {
        return $this->skipped_at !== null;
    }
}
