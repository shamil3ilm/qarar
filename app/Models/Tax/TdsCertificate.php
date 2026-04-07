<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TdsCertificate extends Model
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
            'generated_at' => 'datetime',
            'issued_at'    => 'datetime',
        ];
    }

    public function isIssued(): bool
    {
        return $this->issued_at !== null;
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForQuarter($query, int $quarter, int $year)
    {
        return $query->where('period_quarter', $quarter)->where('period_year', $year);
    }

    public function scopeForDeductee($query, string $deducteeType, int $deducteeId)
    {
        return $query->where('deductee_type', $deducteeType)->where('deductee_id', $deducteeId);
    }
}
