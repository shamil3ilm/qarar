<?php

declare(strict_types=1);

namespace App\Models\Aml;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmlTransactionFlag extends Model
{
    use HasFactory;

    public $timestamps = false;

    // Flag reason constants
    public const LARGE_CASH       = 'large_cash';
    public const STRUCTURING      = 'structuring';
    public const RAPID_MOVEMENT   = 'rapid_movement';
    public const THRESHOLD_BREACH = 'threshold_breach';
    public const UNUSUAL_PATTERN  = 'unusual_pattern';
    public const HIGH_RISK_CONTACT = 'high_risk_contact';

    // Status constants
    public const STATUS_FLAGGED   = 'flagged';
    public const STATUS_CLEARED   = 'cleared';
    public const STATUS_ESCALATED = 'escalated';

    protected $fillable = [
        'organization_id',
        'transaction_type',
        'transaction_id',
        'transaction_number',
        'amount',
        'currency',
        'flag_reason',
        'status',
        'aml_score',
        'context',
        'contact_id',
        'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'context'          => 'array',
            'transaction_date' => 'datetime',
            'created_at'       => 'datetime',
            'amount'           => 'decimal:4',
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
}
