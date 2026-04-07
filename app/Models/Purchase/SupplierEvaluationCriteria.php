<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierEvaluationCriteria extends Model
{
    use BelongsToOrganization, HasFactory;

    public const CATEGORY_QUALITY    = 'quality';
    public const CATEGORY_DELIVERY   = 'delivery';
    public const CATEGORY_PRICE      = 'price';
    public const CATEGORY_SERVICE    = 'service';
    public const CATEGORY_COMPLIANCE = 'compliance';

    protected $table = 'supplier_evaluation_criteria';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'category',
        'weight_percent',
        'is_active',
    ];

    protected $casts = [
        'weight_percent' => 'decimal:2',
        'is_active'      => 'boolean',
    ];

    // Relationships

    public function ratings(): HasMany
    {
        return $this->hasMany(SupplierScorecardRating::class, 'criterion_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
