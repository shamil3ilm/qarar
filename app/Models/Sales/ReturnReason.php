<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ReturnReason extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'description',
        'requires_evidence',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_evidence' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('display_order');
    }
}
