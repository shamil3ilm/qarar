<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TdsReturn extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:4',
            'total_tds'    => 'decimal:4',
            'filed_at'     => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFiled(): bool
    {
        return $this->status === 'filed';
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForQuarter($query, int $quarter, int $year)
    {
        return $query->where('quarter', $quarter)->where('financial_year', $year);
    }
}
