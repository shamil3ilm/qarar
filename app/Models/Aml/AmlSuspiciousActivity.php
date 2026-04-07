<?php

declare(strict_types=1);

namespace App\Models\Aml;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmlSuspiciousActivity extends Model
{
    use HasFactory, HasUuid;

    // Activity type constants
    public const STRUCTURING      = 'structuring';
    public const SMURFING         = 'smurfing';
    public const LAYERING         = 'layering';
    public const UNUSUAL_PATTERN  = 'unusual_pattern';
    public const SANCTIONS_HIT    = 'sanctions_hit';

    // Report type constants
    public const SAR = 'SAR';
    public const CTR = 'CTR';
    public const STR = 'STR';

    // Status constants
    public const STATUS_DRAFT  = 'draft';
    public const STATUS_FILED  = 'filed';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'organization_id',
        'report_type',
        'status',
        'contact_id',
        'contact_name',
        'related_transaction_ids',
        'description',
        'activity_type',
        'total_amount',
        'currency',
        'activity_date_from',
        'activity_date_to',
        'narrative',
        'created_by',
        'filed_by',
        'filed_at',
    ];

    protected function casts(): array
    {
        return [
            'related_transaction_ids' => 'array',
            'activity_date_from'      => 'date',
            'activity_date_to'        => 'date',
            'filed_at'                => 'datetime',
            'total_amount'            => 'decimal:4',
        ];
    }

    // Relationships
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function filer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }
}
