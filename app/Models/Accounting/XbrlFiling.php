<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class XbrlFiling extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REJECTED  = 'rejected';

    public const REPORT_ANNUAL      = 'annual';
    public const REPORT_SEMI_ANNUAL = 'semi_annual';
    public const REPORT_QUARTERLY   = 'quarterly';
    public const REPORT_INTERIM     = 'interim';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start'      => 'date',
            'period_end'        => 'date',
            'validation_errors' => 'array',
            'submitted_at'      => 'datetime',
            'accepted_at'       => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(XbrlTaxonomy::class);
    }

    public function elements(): HasMany
    {
        return $this->hasMany(XbrlFilingElement::class)->orderBy('sequence');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    // -------------------------------------------------------------------------
    // Business logic
    // -------------------------------------------------------------------------

    public function canBeSubmitted(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
