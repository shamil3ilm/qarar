<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityCostEntry extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const CATEGORY_PREVENTION        = 'prevention';
    public const CATEGORY_APPRAISAL         = 'appraisal';
    public const CATEGORY_INTERNAL_FAILURE  = 'internal_failure';
    public const CATEGORY_EXTERNAL_FAILURE  = 'external_failure';

    protected $fillable = [
        'organization_id',
        'cost_category',
        'cost_subcategory',
        'reference_type',
        'reference_id',
        'product_id',
        'period',
        'fiscal_year',
        'amount',
        'description',
        'recorded_by',
    ];

    protected $casts = [
        'period'       => 'integer',
        'fiscal_year'  => 'integer',
        'amount'       => 'decimal:4',
        'reference_id' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeForPeriod(Builder $query, int $period, int $year): Builder
    {
        return $query->where('period', $period)->where('fiscal_year', $year);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('cost_category', $category);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }
}
