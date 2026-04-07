<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RevenueContract extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const METHOD_POINT_IN_TIME = 'point_in_time';
    public const METHOD_OVER_TIME = 'over_time';

    protected $fillable = [
        'organization_id',
        'contract_number',
        'contact_id',
        'contract_date',
        'total_transaction_price',
        'allocated_price',
        'status',
        'recognition_method',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'contract_date'           => 'date',
            'total_transaction_price' => 'decimal:4',
            'allocated_price'         => 'decimal:4',
            'start_date'              => 'date',
            'end_date'                => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function performanceObligations(): HasMany
    {
        return $this->hasMany(PerformanceObligation::class, 'revenue_contract_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getTotalRecognized(): float
    {
        return (float) $this->performanceObligations()->sum('recognized_amount');
    }

    public function getTotalDeferred(): float
    {
        return (float) $this->performanceObligations()->sum('deferred_amount');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForContact($query, int $contactId)
    {
        return $query->where('contact_id', $contactId);
    }
}
