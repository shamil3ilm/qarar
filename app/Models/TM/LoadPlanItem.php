<?php

declare(strict_types=1);

namespace App\Models\TM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadPlanItem extends Model
{
    protected $table = 'tm_load_plan_items';

    protected $fillable = [
        'load_plan_id',
        'transportation_order_id',
        'loading_sequence',
    ];

    protected $casts = [
        'loading_sequence' => 'integer',
    ];

    public function loadPlan(): BelongsTo
    {
        return $this->belongsTo(LoadPlan::class, 'load_plan_id');
    }

    public function transportationOrder(): BelongsTo
    {
        return $this->belongsTo(TransportationOrder::class, 'transportation_order_id');
    }
}
