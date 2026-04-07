<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdiMessageSegment extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'segment_data'     => 'array',
            'segment_sequence' => 'integer',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(EdiMessage::class, 'edi_message_id');
    }
}
