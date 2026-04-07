<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FxHedgeRelation extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    protected $table = 'fx_hedge_relations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'hedge_ratio'        => 'decimal:4',
            'designation_date'   => 'date',
            'dedesignation_date' => 'date',
        ];
    }

    public function fxForward(): BelongsTo
    {
        return $this->belongsTo(FxForward::class, 'fx_forward_id');
    }
}
