<?php

declare(strict_types=1);

namespace App\Models\Budget;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetRevision extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    protected $table = 'budget_revisions';

    protected $guarded = ['id'];

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_APPROVED = 'approved';

    protected function casts(): array
    {
        return [
            'previous_total'  => 'decimal:2',
            'new_total'       => 'decimal:2',
            'approved_at'     => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetRevisionLine::class, 'budget_revision_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
