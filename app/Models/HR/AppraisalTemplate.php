<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppraisalTemplate extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'rating_scale',
        'is_default',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'rating_scale' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ---------------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------------

    public function sections(): HasMany
    {
        return $this->hasMany(AppraisalTemplateSection::class)->orderBy('sort_order');
    }

    public function sectionsWithQuestions(): HasMany
    {
        return $this->hasMany(AppraisalTemplateSection::class)
            ->orderBy('sort_order')
            ->with(['questions' => fn ($q) => $q->orderBy('sort_order')]);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ---------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}
