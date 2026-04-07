<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Accounting\FiscalYear;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostVersion extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const TYPE_STANDARD = 'standard';
    public const TYPE_ACTUAL   = 'actual';
    public const TYPE_PLANNED  = 'planned';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
        'marked_at'  => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function productCosts(): HasMany
    {
        return $this->hasMany(ProductCost::class);
    }

    public function rollupLogs(): HasMany
    {
        return $this->hasMany(CostRollupLog::class);
    }

    public function productionVariances(): HasMany
    {
        return $this->hasMany(ProductionVariance::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('costing_type', $type);
    }
}
