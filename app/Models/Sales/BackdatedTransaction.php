<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class BackdatedTransaction extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'transaction_type',
        'transaction_id',
        'transaction_date',
        'entry_date',
        'reason',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'entry_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    // Relationships
    public function transaction(): MorphTo
    {
        return $this->morphTo();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Business logic
    public function isApproved(): bool
    {
        return $this->approved_by !== null;
    }

    public function isPending(): bool
    {
        return $this->approved_by === null;
    }

    public function getDaysDifference(): int
    {
        return $this->transaction_date->diffInDays($this->entry_date);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->whereNull('approved_by');
    }

    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_by');
    }

    public function scopeByTransactionType($query, string $type)
    {
        return $query->where('transaction_type', $type);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    public function scopeCreatedBy($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }
}
