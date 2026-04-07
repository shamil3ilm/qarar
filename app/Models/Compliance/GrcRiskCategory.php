<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrcRiskCategory extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'grc_risk_categories';

    public const TYPE_STRATEGIC    = 'strategic';
    public const TYPE_OPERATIONAL  = 'operational';
    public const TYPE_FINANCIAL    = 'financial';
    public const TYPE_COMPLIANCE   = 'compliance';
    public const TYPE_REPUTATIONAL = 'reputational';
    public const TYPE_IT           = 'it';
    public const TYPE_EHS          = 'ehs';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'parent_id',
        'risk_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function risks(): HasMany
    {
        return $this->hasMany(GrcRisk::class, 'category_id');
    }
}
