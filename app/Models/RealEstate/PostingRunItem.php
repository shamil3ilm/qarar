<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostingRunItem extends Model
{
    protected $table = 're_posting_run_items';

    protected $fillable = [
        'posting_run_id',
        'contract_id',
        'condition_id',
        'condition_type',
        'amount',
        'tax_amount',
        'total_amount',
        'status',
        'error_message',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
    ];

    public function postingRun(): BelongsTo
    {
        return $this->belongsTo(PostingRun::class, 'posting_run_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class, 'contract_id');
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(ContractCondition::class, 'condition_id');
    }
}
