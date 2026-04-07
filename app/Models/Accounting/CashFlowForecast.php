<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashFlowForecast extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'forecast_date'         => 'date',
            'horizon_days'          => 'integer',
            'total_opening_balance' => 'decimal:4',
            'total_inflows'         => 'decimal:4',
            'total_outflows'        => 'decimal:4',
            'closing_balance'       => 'decimal:4',
            'generated_at'          => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(CashFlowScenario::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CashFlowLine::class, 'forecast_id');
    }

    public function inflows(): HasMany
    {
        return $this->hasMany(CashFlowLine::class, 'forecast_id')
            ->where('flow_type', CashFlowLine::TYPE_INFLOW);
    }

    public function outflows(): HasMany
    {
        return $this->hasMany(CashFlowLine::class, 'forecast_id')
            ->where('flow_type', CashFlowLine::TYPE_OUTFLOW);
    }

    public function netPosition(): float
    {
        return (float) bcsub((string) $this->total_inflows, (string) $this->total_outflows, 4);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
