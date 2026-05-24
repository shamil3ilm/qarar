<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialCloseTemplate extends Model
{
    use HasFactory;
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    public const TYPE_MONTH_END   = 'month_end';
    public const TYPE_QUARTER_END = 'quarter_end';
    public const TYPE_YEAR_END    = 'year_end';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'close_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(FinancialCloseTemplateTask::class)->orderBy('sort_order');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(FinancialClosePeriod::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
