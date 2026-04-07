<?php

declare(strict_types=1);

namespace App\Models\Automation;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class AutomationRule extends Model
{
    use HasFactory;
    use BelongsToOrganization, HasUuid;

    // Trigger types
    public const TRIGGER_EVENT = 'event';
    public const TRIGGER_SCHEDULE = 'schedule';
    public const TRIGGER_MANUAL = 'manual';

    // Entity types
    public const ENTITY_INVOICE = 'invoice';
    public const ENTITY_CUSTOMER = 'customer';
    public const ENTITY_EXPENSE = 'expense';
    public const ENTITY_PAYMENT = 'payment';
    public const ENTITY_QUOTATION = 'quotation';
    public const ENTITY_PURCHASE_ORDER = 'purchase_order';
    public const ENTITY_BILL = 'bill';
    public const ENTITY_LEAD = 'lead';
    public const ENTITY_OPPORTUNITY = 'opportunity';
    public const ENTITY_EMPLOYEE = 'employee';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'trigger_type',
        'trigger_event',
        'trigger_schedule',
        'entity_type',
        'conditions',
        'actions',
        'priority',
        'stop_on_match',
        'is_active',
        'execution_count',
        'last_executed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'priority' => 'integer',
            'stop_on_match' => 'boolean',
            'is_active' => 'boolean',
            'execution_count' => 'integer',
            'last_executed_at' => 'datetime',
        ];
    }

    // Relationships

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationRuleLog::class, 'rule_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(AutomationSchedule::class, 'rule_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeForTriggerType(Builder $query, string $triggerType): Builder
    {
        return $query->where('trigger_type', $triggerType);
    }

    public function scopeForTriggerEvent(Builder $query, string $event): Builder
    {
        return $query->where('trigger_event', $event);
    }

    public function scopeForEntityType(Builder $query, string $entityType): Builder
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority', 'desc');
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('trigger_type', self::TRIGGER_SCHEDULE);
    }

    public function scopeEventDriven(Builder $query): Builder
    {
        return $query->where('trigger_type', self::TRIGGER_EVENT);
    }

    // Helpers

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isScheduled(): bool
    {
        return $this->trigger_type === self::TRIGGER_SCHEDULE;
    }

    public function isEventDriven(): bool
    {
        return $this->trigger_type === self::TRIGGER_EVENT;
    }

    public function isManual(): bool
    {
        return $this->trigger_type === self::TRIGGER_MANUAL;
    }

    public function shouldStopOnMatch(): bool
    {
        return $this->stop_on_match;
    }

    public function incrementExecutionCount(): void
    {
        $this->increment('execution_count');
        $this->update(['last_executed_at' => now()]);
    }

    public static function getTriggerTypes(): array
    {
        return [
            self::TRIGGER_EVENT,
            self::TRIGGER_SCHEDULE,
            self::TRIGGER_MANUAL,
        ];
    }

    public static function getEntityTypes(): array
    {
        return [
            self::ENTITY_INVOICE,
            self::ENTITY_CUSTOMER,
            self::ENTITY_EXPENSE,
            self::ENTITY_PAYMENT,
            self::ENTITY_QUOTATION,
            self::ENTITY_PURCHASE_ORDER,
            self::ENTITY_BILL,
            self::ENTITY_LEAD,
            self::ENTITY_OPPORTUNITY,
            self::ENTITY_EMPLOYEE,
        ];
    }
}
