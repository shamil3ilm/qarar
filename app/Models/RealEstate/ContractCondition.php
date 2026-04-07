<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractCondition extends Model
{
    protected $table = 're_contract_conditions';

    protected $fillable = [
        'contract_id',
        'condition_type',
        'description',
        'amount',
        'basis',
        'valid_from',
        'valid_to',
        'escalation_type',
        'escalation_rate',
        'escalation_index',
        'escalation_frequency',
        'next_escalation_date',
        'is_taxable',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'escalation_rate' => 'decimal:4',
        'next_escalation_date' => 'date',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class, 'contract_id');
    }

    public function computeAmount(float $areaSqm = 0): string
    {
        return match ($this->basis) {
            'per_sqm' => bcmul((string) $this->amount, (string) $areaSqm, 4),
            default => (string) $this->amount,
        };
    }
}
