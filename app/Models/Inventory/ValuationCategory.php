<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ValuationCategory extends Model
{
    use BelongsToOrganization;

    protected $table = 'inventory_valuation_categories';

    protected $guarded = ['id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function valuationTypes(): HasMany
    {
        return $this->hasMany(ValuationType::class, 'valuation_category_id');
    }
}
