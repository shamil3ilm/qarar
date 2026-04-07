<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DpsScreeningResult extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'dps_screening_results';

    public const FIELD_NAME      = 'name';
    public const FIELD_ALIAS     = 'alias';
    public const FIELD_ID_NUMBER = 'id_number';
    public const FIELD_ADDRESS   = 'address';

    protected $fillable = [
        'dps_screening_run_id',
        'dps_list_entry_id',
        'match_score',
        'matched_field',
        'is_false_positive',
    ];

    protected function casts(): array
    {
        return [
            'match_score'      => 'decimal:2',
            'is_false_positive' => 'boolean',
        ];
    }

    public function screeningRun(): BelongsTo
    {
        return $this->belongsTo(DpsScreeningRun::class, 'dps_screening_run_id');
    }

    public function listEntry(): BelongsTo
    {
        return $this->belongsTo(DpsListEntry::class, 'dps_list_entry_id');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('is_false_positive', false);
    }
}
