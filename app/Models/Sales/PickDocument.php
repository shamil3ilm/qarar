<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PickDocument extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'organization_id',
        'pick_number',
        'delivery_document_id',
        'assigned_to',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function deliveryDocument(): BelongsTo
    {
        return $this->belongsTo(DeliveryDocument::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PickDocumentLine::class);
    }
}
