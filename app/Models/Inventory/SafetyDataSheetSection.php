<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafetyDataSheetSection extends Model
{
    use HasUuid;

    protected $table = 'safety_data_sheet_sections';

    protected $fillable = [
        'safety_data_sheet_id',
        'section_number',
        'section_title',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'section_number' => 'integer',
        ];
    }

    public function safetyDataSheet(): BelongsTo
    {
        return $this->belongsTo(SafetyDataSheet::class);
    }
}
