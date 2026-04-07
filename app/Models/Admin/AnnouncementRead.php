<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class AnnouncementRead extends Model
{
    use HasFactory;
    protected $fillable = [
        'announcement_id',
        'user_id',
        'is_dismissed',
    ];

    protected function casts(): array
    {
        return [
            'is_dismissed' => 'boolean',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(SystemAnnouncement::class, 'announcement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
