<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CpqConfigurationItem extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'cpq_configuration_items';

    protected $fillable = [
        'cpq_configuration_id',
        'cpq_option_group_id',
        'cpq_option_id',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:4',
            'unit_price' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(CpqConfiguration::class, 'cpq_configuration_id');
    }

    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(CpqOptionGroup::class, 'cpq_option_group_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(CpqOption::class, 'cpq_option_id');
    }
}
