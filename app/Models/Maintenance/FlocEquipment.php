<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FlocEquipment extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'floc_id', 'equipment_number', 'description',
        'category', 'manufacturer', 'model', 'serial_number',
        'installed_at', 'removed_at', 'product_id',
    ];

    protected $casts = [
        'installed_at' => 'date',
        'removed_at'   => 'date',
    ];

    public function functionalLocation(): BelongsTo
    {
        return $this->belongsTo(FunctionalLocation::class, 'floc_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
