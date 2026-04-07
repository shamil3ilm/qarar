<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingTemplate extends Model
{
    use HasFactory;
    use HasUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'order'     => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(OnboardingStep::class, 'template_id')->orderBy('order');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(UserOnboardingProgress::class, 'template_id');
    }
}
