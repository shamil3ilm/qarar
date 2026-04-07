<?php

declare(strict_types=1);

namespace App\Models\CRM;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceTicketComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ServiceTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isInternal(): bool
    {
        return $this->is_internal;
    }

    public function isCustomerVisible(): bool
    {
        return !$this->is_internal;
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function scopeCustomerVisible($query)
    {
        return $query->where('is_internal', false);
    }
}
