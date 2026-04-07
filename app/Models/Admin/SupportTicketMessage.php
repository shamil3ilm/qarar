<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class SupportTicketMessage extends Model
{
    use HasFactory;
    protected $fillable = [
        'ticket_id',
        'user_id',
        'admin_id',
        'message',
        'is_internal_note',
        'attachments',
    ];

    protected function casts(): array
    {
        return [
            'is_internal_note' => 'boolean',
            'attachments' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'admin_id');
    }

    public function isFromAdmin(): bool
    {
        return $this->admin_id !== null;
    }

    public function isFromUser(): bool
    {
        return $this->user_id !== null;
    }

    public function scopePublic($query)
    {
        return $query->where('is_internal_note', false);
    }

    public function scopeInternalNotes($query)
    {
        return $query->where('is_internal_note', true);
    }
}
