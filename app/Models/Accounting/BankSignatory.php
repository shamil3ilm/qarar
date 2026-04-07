<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankSignatory extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    public const AUTHORITY_SINGLE    = 'single';
    public const AUTHORITY_JOINT_ANY = 'joint_any';
    public const AUTHORITY_JOINT_ALL = 'joint_all';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'signing_limit' => 'decimal:4',
            'valid_from'    => 'date',
            'valid_to'      => 'date',
            'is_active'     => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('valid_from', '<=', now()->toDateString())
            ->where(function (Builder $q): void {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now()->toDateString());
            });
    }

    // -------------------------------------------------------------------------
    // Business logic
    // -------------------------------------------------------------------------

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->toDateString();

        if ($this->valid_from->toDateString() > $today) {
            return false;
        }

        if ($this->valid_to !== null && $this->valid_to->toDateString() < $today) {
            return false;
        }

        return true;
    }
}
