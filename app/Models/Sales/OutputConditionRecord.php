<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutputConditionRecord extends Model
{
    use HasFactory;

    public const KEY_CUSTOMER       = 'customer';
    public const KEY_CUSTOMER_GROUP = 'customer_group';
    public const KEY_ALL            = 'all';

    protected $fillable = [
        'output_type_id',
        'key_combination',
        'customer_id',
        'customer_group_id',
        'is_active',
        'valid_from',
        'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'valid_from' => 'date',
            'valid_to'   => 'date',
        ];
    }

    public function outputType(): BelongsTo
    {
        return $this->belongsTo(OutputType::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValidOn($query, string $date)
    {
        return $query->where(function ($q) use ($date) {
            $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
        });
    }
}
