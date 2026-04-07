<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintCommunication extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'complaint_id', 'direction', 'channel',
        'content', 'user_id', 'communicated_at',
    ];

    protected $casts = ['communicated_at' => 'datetime'];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
