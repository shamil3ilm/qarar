<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrcKriReading extends Model
{
    use HasFactory;

    protected $table = 'grc_kri_readings';

    protected $fillable = [
        'kri_id',
        'reading_date',
        'value',
        'status',
        'notes',
        'is_auto',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'reading_date' => 'date',
            'value'        => 'decimal:4',
            'is_auto'      => 'boolean',
        ];
    }

    public function kri(): BelongsTo
    {
        return $this->belongsTo(GrcKri::class, 'kri_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
