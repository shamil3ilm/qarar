<?php

declare(strict_types=1);

namespace App\Models\Expense;

use App\Models\Accounting\Account;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ExpenseCategory extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'parent_id',
        'name',
        'code',
        'icon',
        'color',
        'description',
        'default_account_id',
        'is_active',
        'requires_receipt',
        'budget_limit',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_receipt' => 'boolean',
            'budget_limit' => 'decimal:2',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(ExpenseBudget::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }
}
