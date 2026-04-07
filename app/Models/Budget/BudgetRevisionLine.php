<?php

declare(strict_types=1);

namespace App\Models\Budget;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetRevisionLine extends Model
{
    use HasFactory;

    protected $table = 'budget_revision_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'old_value' => 'decimal:2',
            'new_value' => 'decimal:2',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function revision(): BelongsTo
    {
        return $this->belongsTo(BudgetRevision::class, 'budget_revision_id');
    }

    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class, 'budget_line_id');
    }
}
