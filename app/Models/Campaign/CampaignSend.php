<?php

declare(strict_types=1);

namespace App\Models\Campaign;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignSend extends Model
{
    use HasFactory, HasUuid;

    public $timestamps = false;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT    = 'sent';
    public const STATUS_FAILED  = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'campaign_id',
        'user_id',
        'organization_id',
        'status',
        'channel',
        'sent_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at'  => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
