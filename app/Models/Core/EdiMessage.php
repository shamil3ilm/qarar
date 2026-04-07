<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EdiMessage extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public const DIRECTION_INBOUND  = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';

    public const STATUS_RECEIVED       = 'received';
    public const STATUS_PROCESSING     = 'processing';
    public const STATUS_PROCESSED      = 'processed';
    public const STATUS_FAILED         = 'failed';
    public const STATUS_SENT           = 'sent';
    public const STATUS_ACKNOWLEDGED   = 'acknowledged';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'parsed_content' => 'array',
            'reference_id'   => 'integer',
            'received_at'    => 'datetime',
            'processed_at'   => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(EdiPartner::class, 'edi_partner_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(EdiMessageSegment::class);
    }

    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', self::DIRECTION_INBOUND);
    }

    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', self::DIRECTION_OUTBOUND);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_RECEIVED, self::STATUS_PROCESSING]);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }
}
