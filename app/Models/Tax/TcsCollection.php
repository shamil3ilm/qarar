<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TcsCollection extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'collection_date'   => 'date',
            'collection_amount' => 'decimal:4',
            'tcs_rate'          => 'decimal:2',
            'tcs_amount'        => 'decimal:4',
            'deposited'         => 'boolean',
        ];
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeUndeposited($query)
    {
        return $query->where('deposited', false);
    }

    public function scopeForPeriod($query, string $start, string $end)
    {
        return $query->whereBetween('collection_date', [$start, $end]);
    }
}
