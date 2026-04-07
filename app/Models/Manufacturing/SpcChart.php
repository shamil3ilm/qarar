<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpcChart extends Model
{
    use HasUuid;

    protected $table = 'spc_charts';

    protected $fillable = [
        'organization_id',
        'product_id',
        'characteristic_name',
        'chart_type',
        'subgroup_size',
        'ucl',
        'lcl',
        'center_line',
        'usl',
        'lsl',
        'cpk',
        'is_active',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'subgroup_size'  => 'integer',
        'ucl'            => 'decimal:6',
        'lcl'            => 'decimal:6',
        'center_line'    => 'decimal:6',
        'usl'            => 'decimal:6',
        'lsl'            => 'decimal:6',
        'cpk'            => 'decimal:4',
    ];

    public function subgroups(): HasMany
    {
        return $this->hasMany(SpcSubgroup::class, 'spc_chart_id');
    }
}
