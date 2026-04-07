<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialStatementVersion extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const TYPE_BALANCE_SHEET = 'balance_sheet';
    public const TYPE_INCOME_STATEMENT = 'income_statement';
    public const TYPE_CASH_FLOW = 'cash_flow';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'type',
        'is_default',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(FinancialStatementVersionNode::class, 'fsv_id')
            ->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
