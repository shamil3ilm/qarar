<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ValuationType extends Model
{
    use BelongsToOrganization;

    protected $table = 'inventory_valuation_types';

    protected $guarded = ['id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ValuationCategory::class, 'valuation_category_id');
    }

    public function splitValuations(): HasMany
    {
        return $this->hasMany(SplitValuation::class, 'valuation_type_id');
    }
}
