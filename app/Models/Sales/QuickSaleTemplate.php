<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class QuickSaleTemplate extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'default_items',
        'default_customer_id',
        'default_payment_method',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_items' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function defaultCustomer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'default_customer_id');
    }

    // Business logic
    public function getItemCount(): int
    {
        return count($this->default_items ?? []);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }
}
