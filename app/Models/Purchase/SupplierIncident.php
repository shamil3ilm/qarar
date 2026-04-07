<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierIncident extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const TYPE_LATE_DELIVERY     = 'late_delivery';
    public const TYPE_QUALITY_ISSUE     = 'quality_issue';
    public const TYPE_PRICING_DISPUTE   = 'pricing_dispute';
    public const TYPE_COMPLIANCE_BREACH = 'compliance_breach';
    public const TYPE_COMMUNICATION     = 'communication';

    public const SEVERITY_LOW      = 'low';
    public const SEVERITY_MEDIUM   = 'medium';
    public const SEVERITY_HIGH     = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'organization_id',
        'supplier_id',
        'incident_type',
        'severity',
        'description',
        'occurred_at',
        'resolved_at',
        'resolution_notes',
        'created_by',
    ];

    protected $casts = [
        'occurred_at' => 'date',
        'resolved_at' => 'date',
    ];

    // Relationships

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes

    public function scopeOpen($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeOfSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    // Helpers

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function getResolutionDays(): ?int
    {
        if (!$this->isResolved()) {
            return null;
        }

        return (int) $this->occurred_at->diffInDays($this->resolved_at);
    }
}
