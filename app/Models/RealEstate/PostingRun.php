<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostingRun extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 're_posting_runs';

    protected $fillable = [
        'organization_id',
        'run_number',
        'type',
        'posting_date',
        'period_year',
        'period_month',
        'status',
        'contracts_processed',
        'total_amount',
        'currency_code',
        'executed_by',
        'executed_at',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'period_year' => 'integer',
        'period_month' => 'integer',
        'contracts_processed' => 'integer',
        'total_amount' => 'decimal:4',
        'executed_at' => 'datetime',
    ];

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PostingRunItem::class, 'posting_run_id');
    }
}
