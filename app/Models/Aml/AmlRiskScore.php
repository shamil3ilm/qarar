<?php

declare(strict_types=1);

namespace App\Models\Aml;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmlRiskScore extends Model
{
    use HasFactory;

    // Risk level constants
    public const LOW      = 'low';
    public const MEDIUM   = 'medium';
    public const HIGH     = 'high';
    public const CRITICAL = 'critical';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'score',
        'risk_level',
        'score_breakdown',
        'sanctions_hit',
        'pep_hit',
        'sanctions_details',
        'last_screened_at',
        'score_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'score_breakdown'  => 'array',
            'sanctions_hit'    => 'boolean',
            'pep_hit'          => 'boolean',
            'last_screened_at' => 'datetime',
            'score_updated_at' => 'datetime',
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

    /**
     * Determine risk level from a numeric score.
     * 0-30 = low, 31-60 = medium, 61-80 = high, 81-100 = critical
     */
    public static function getRiskLevel(int $score): string
    {
        return match (true) {
            $score <= 30 => self::LOW,
            $score <= 60 => self::MEDIUM,
            $score <= 80 => self::HIGH,
            default      => self::CRITICAL,
        };
    }
}
