<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CpqOptionGroup extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'cpq_option_groups';

    public const SELECTION_SINGLE = 'single';
    public const SELECTION_MULTI  = 'multi';

    protected $fillable = [
        'cpq_configurable_product_id',
        'group_code',
        'name',
        'selection_type',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required'  => 'boolean',
            'sort_order'   => 'integer',
        ];
    }

    public function configurableProduct(): BelongsTo
    {
        return $this->belongsTo(CpqConfigurableProduct::class, 'cpq_configurable_product_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(CpqOption::class)->orderBy('sort_order');
    }

    public function isMultiSelect(): bool
    {
        return $this->selection_type === self::SELECTION_MULTI;
    }
}
