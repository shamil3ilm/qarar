<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialStatementVersionNode extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'fsv_id',
        'parent_id',
        'account_id',
        'node_type',
        'label',
        'sort_order',
        'sign',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'sign' => 'integer',
        ];
    }

    public function fsv(): BelongsTo
    {
        return $this->belongsTo(FinancialStatementVersion::class, 'fsv_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')
            ->orderBy('sort_order');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
