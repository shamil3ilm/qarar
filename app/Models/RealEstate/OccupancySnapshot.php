<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class OccupancySnapshot extends Model
{
    use BelongsToOrganization;

    protected $table = 're_occupancy_snapshots';

    protected $guarded = ['id'];

    public const TYPE_BUILDING  = 'building';
    public const TYPE_PROPERTY  = 'property';
    public const TYPE_PORTFOLIO = 'portfolio';

    protected function casts(): array
    {
        return [
            'snapshot_date'        => 'date',
            'total_units'          => 'integer',
            'occupied_units'       => 'integer',
            'vacant_units'         => 'integer',
            'occupancy_rate'       => 'decimal:2',
            'total_area_sqm'       => 'decimal:4',
            'occupied_area_sqm'    => 'decimal:4',
            'area_occupancy_rate'  => 'decimal:2',
            'potential_rent'       => 'decimal:2',
            'actual_rent'          => 'decimal:2',
        ];
    }
}
