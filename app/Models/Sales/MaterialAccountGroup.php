<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialAccountGroup extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'group_code',
        'description',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'material_account_group_id');
    }
}
