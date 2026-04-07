<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CpqConstraintRule extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'cpq_constraint_rules';

    public const TYPE_REQUIRES = 'requires';
    public const TYPE_EXCLUDES = 'excludes';
    public const TYPE_INCLUDES = 'includes';

    protected $fillable = [
        'cpq_configurable_product_id',
        'rule_type',
        'if_option_id',
        'then_option_id',
        'error_message',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function configurableProduct(): BelongsTo
    {
        return $this->belongsTo(CpqConfigurableProduct::class, 'cpq_configurable_product_id');
    }

    public function ifOption(): BelongsTo
    {
        return $this->belongsTo(CpqOption::class, 'if_option_id');
    }

    public function thenOption(): BelongsTo
    {
        return $this->belongsTo(CpqOption::class, 'then_option_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
