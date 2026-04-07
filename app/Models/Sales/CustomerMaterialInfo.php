<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMaterialInfo extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'contact_id',
        'product_id',
        'customer_material_number',
        'customer_material_description',
        'delivery_lead_time_days',
        'minimum_order_quantity',
        'unit_of_measure',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'delivery_lead_time_days' => 'integer',
            'minimum_order_quantity'  => 'decimal:4',
            'is_active'               => 'boolean',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForContact($query, int $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }
}
