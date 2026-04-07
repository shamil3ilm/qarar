<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\Account;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueAccountDeterminationKey extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'customer_account_group',
        'material_account_group',
        'condition_type',
        'gl_account_id',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
    ];

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    /**
     * Scope to find an active key for given groups and condition type.
     */
    public function scopeActiveFor(
        $query,
        ?string $custGroup,
        ?string $matGroup,
        ?string $condType
    ): void {
        $today = now()->toDateString();
        $query->where(function ($q) use ($custGroup): void {
            $q->whereNull('customer_account_group')
              ->orWhere('customer_account_group', $custGroup);
        })->where(function ($q) use ($matGroup): void {
            $q->whereNull('material_account_group')
              ->orWhere('material_account_group', $matGroup);
        })->where(function ($q) use ($condType): void {
            $q->whereNull('condition_type')
              ->orWhere('condition_type', $condType);
        })->where('valid_from', '<=', $today)
          ->where(function ($q) use ($today): void {
              $q->whereNull('valid_to')
                ->orWhere('valid_to', '>=', $today);
          });
    }
}
