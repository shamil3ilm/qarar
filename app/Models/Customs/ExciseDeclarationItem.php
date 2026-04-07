<?php

declare(strict_types=1);

namespace App\Models\Customs;

use App\Models\Customs\ExciseCategory;
use App\Models\Customs\ExciseRate;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExciseDeclarationItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function exciseCategory(): BelongsTo
    {
        return $this->belongsTo(ExciseCategory::class, 'excise_category_id');
    }

    public function exciseRate(): BelongsTo
    {
        return $this->belongsTo(ExciseRate::class, 'excise_rate_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}