<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DimOrganization extends Model
{
    protected $table = 'dim_organization';

    protected $fillable = [
        'organization_id',
        'org_name',
        'org_type',
        'country_code',
        'currency_code',
        'fiscal_year_start_month',
    ];

    protected $casts = [
        'fiscal_year_start_month' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
