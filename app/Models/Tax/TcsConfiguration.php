<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TcsConfiguration extends Model
{
    use HasFactory;
    use HasUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate'      => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
