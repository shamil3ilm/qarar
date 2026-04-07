<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrpDemandItem extends Model
{
    use HasFactory;

    public const SOURCE_SALES_ORDER = 'sales_order';
    public const SOURCE_FORECAST = 'forecast';
    public const SOURCE_SAFETY_STOCK = 'safety_stock';
    public const SOURCE_BOM = 'bom';

    protected $fillable = [
        'mrp_run_id',
        'product_id',
        'source_type',
        'source_id',
        'required_date',
        'required_quantity',
    ];

    protected function casts(): array
    {
        return [
            'required_date'     => 'date',
            'required_quantity' => 'decimal:4',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(MrpRun::class, 'mrp_run_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
