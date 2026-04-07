<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopaDimension extends Model
{
    public const TYPE_PRODUCT        = 'product';
    public const TYPE_CUSTOMER       = 'customer';
    public const TYPE_REGION         = 'region';
    public const TYPE_SALES_CHANNEL  = 'sales_channel';
    public const TYPE_MATERIAL_GROUP = 'material_group';

    protected $fillable = [
        'organization_id',
        'dimension_type',
        'dimension_value',
        'dimension_label',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Core\Organization::class, 'organization_id');
    }
}
