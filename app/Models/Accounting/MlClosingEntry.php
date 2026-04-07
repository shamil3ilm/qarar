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

class MlClosingEntry extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period'                  => 'integer',
            'fiscal_year'             => 'integer',
            'total_price_difference'  => 'decimal:4',
            'revaluation_amount'      => 'decimal:4',
            'actual_price_calculated' => 'decimal:4',
            'run_at'                  => 'datetime',
        ];
    }

    public function materialLedgerRecord(): BelongsTo
    {
        return $this->belongsTo(MaterialLedgerRecord::class);
    }

    public function runBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }

    public function priceDifferences(): HasMany
    {
        return $this->hasMany(MlPriceDifference::class);
    }
}
