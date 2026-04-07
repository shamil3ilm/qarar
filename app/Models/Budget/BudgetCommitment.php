<?php

declare(strict_types=1);

namespace App\Models\Budget;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetCommitment extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    protected $table = 'budget_commitments';

    protected $guarded = ['id'];

    public const STATUS_OPEN           = 'open';
    public const STATUS_PARTIALLY_USED = 'partially_used';
    public const STATUS_USED           = 'used';
    public const STATUS_CANCELLED      = 'cancelled';

    protected function casts(): array
    {
        return [
            'committed_amount' => 'decimal:2',
            'committed_at'     => 'datetime',
            'released_at'      => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class, 'budget_line_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_PARTIALLY_USED], true);
    }

    /**
     * Release a portion of the commitment against the actual spend.
     * Decrements the committed amount on the budget line and marks
     * this commitment as partially used or fully used.
     */
    public function partialRelease(float $actualAmount, int $userId): self
    {
        $committed  = (float) $this->committed_amount;
        $newStatus  = $actualAmount >= $committed
            ? self::STATUS_USED
            : self::STATUS_PARTIALLY_USED;

        $this->update([
            'status'      => $newStatus,
            'released_at' => now(),
        ]);

        // Decrement committed amount and add actual to budget line
        $line = $this->budgetLine;
        $line->decrement('committed_amount', min($actualAmount, $committed));
        $line->increment('actual_amount', $actualAmount);

        return $this->fresh();
    }
}
