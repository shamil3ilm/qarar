<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'statement_date'    => 'date',
            'completed_at'      => 'datetime',
            'statement_balance' => 'decimal:4',
            'book_balance'      => 'decimal:4',
            'difference'        => 'decimal:4',
        ];
    }

    // Status values
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public function items(): HasMany
    {
        return $this->hasMany(BankReconciliationItem::class, 'reconciliation_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isBalanced(): bool
    {
        return bccomp((string) abs((float) ($this->difference ?? 0)), '0.0001', 4) <= 0;
    }

    public function calculateDifference(): void
    {
        $clearedAmount = $this->items()->where('is_cleared', true)->sum('amount');
        $this->difference = (float) bcsub(
            (string) $this->statement_balance,
            (string) bcadd((string) $this->book_balance, (string) $clearedAmount, 4),
            4
        );
        $this->save();
    }
}