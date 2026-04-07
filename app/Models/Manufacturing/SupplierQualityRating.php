<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierQualityRating extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'organization_id', 'supplier_id', 'rating_period_start', 'rating_period_end',
        'quality_score', 'delivery_score', 'price_score', 'overall_score',
        'classification', 'notes', 'evaluated_by_id',
    ];

    protected $casts = [
        'rating_period_start' => 'date',
        'rating_period_end'   => 'date',
        'quality_score'       => 'decimal:2',
        'delivery_score'      => 'decimal:2',
        'price_score'         => 'decimal:2',
        'overall_score'       => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by_id');
    }
}
