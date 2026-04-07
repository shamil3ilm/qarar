<?php

declare(strict_types=1);

namespace App\Models\CRM;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaPolicy extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'priority',
        'first_response_hours',
        'resolution_hours',
        'business_hours_only',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'first_response_hours' => 'integer',
            'resolution_hours' => 'integer',
            'business_hours_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class, 'sla_policy_id');
    }

    /**
     * Calculate SLA deadlines based on the policy settings.
     * When business_hours_only is true, adds hours only during Mon-Fri 09:00-17:00.
     *
     * @return array{first_response_due_at: Carbon, resolution_due_at: Carbon}
     */
    public function calculateDeadlines(Carbon $createdAt): array
    {
        return [
            'first_response_due_at' => $this->addBusinessHours($createdAt->copy(), $this->first_response_hours),
            'resolution_due_at'     => $this->addBusinessHours($createdAt->copy(), $this->resolution_hours),
        ];
    }

    private function addBusinessHours(Carbon $from, int $hours): Carbon
    {
        if (!$this->business_hours_only) {
            return $from->addHours($hours);
        }

        $remaining = $hours;
        $current = $from->copy();

        // Business hours: Monday-Friday 09:00-17:00
        $businessStart = 9;
        $businessEnd = 17;

        while ($remaining > 0) {
            // Skip weekends
            if ($current->isWeekend()) {
                $current->addDay()->setTime($businessStart, 0);
                continue;
            }

            // If before business hours, jump to start
            if ($current->hour < $businessStart) {
                $current->setTime($businessStart, 0);
            }

            // If after business hours, jump to next business day start
            if ($current->hour >= $businessEnd) {
                $current->addDay()->setTime($businessStart, 0);
                continue;
            }

            // Hours remaining today
            $hoursLeftToday = $businessEnd - $current->hour - ($current->minute > 0 ? 1 : 0);
            if ($hoursLeftToday <= 0) {
                $current->addDay()->setTime($businessStart, 0);
                continue;
            }

            if ($remaining <= $hoursLeftToday) {
                $current->addHours($remaining);
                $remaining = 0;
            } else {
                $remaining -= $hoursLeftToday;
                $current->addDay()->setTime($businessStart, 0);
            }
        }

        return $current;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }
}
