<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\GrcKri;
use App\Models\Compliance\GrcKriReading;
use App\Services\Core\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class KriService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * @param array{risk_id?: int, last_status?: string, is_active?: bool, per_page?: int} $filters
     */
    public function listKris(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = GrcKri::where('organization_id', $organizationId)
            ->with(['risk', 'owner']);

        if (isset($filters['risk_id'])) {
            $query->where('risk_id', (int) $filters['risk_id']);
        }

        if (!empty($filters['last_status'])) {
            $query->where('last_status', $filters['last_status']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderBy('name')->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function createKri(int $organizationId, array $data, int $userId): GrcKri
    {
        return GrcKri::create(array_merge($data, [
            'organization_id' => $organizationId,
            'created_by'      => $userId,
            'owner_id'        => $data['owner_id'] ?? $userId,
        ]));
    }

    public function findKri(int $organizationId, string $uuid): GrcKri
    {
        return GrcKri::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->with(['risk', 'owner', 'readings' => fn ($q) => $q->orderByDesc('reading_date')->limit(12)])
            ->firstOrFail();
    }

    /**
     * Record a manual KRI reading, compute its status, and update the KRI's last_* fields.
     */
    public function recordReading(int $organizationId, string $uuid, float $value, int $userId, ?string $notes = null): GrcKriReading
    {
        $kri    = GrcKri::where('organization_id', $organizationId)->where('uuid', $uuid)->firstOrFail();
        $status = $this->computeStatus($kri, $value);

        $reading = GrcKriReading::create([
            'kri_id'       => $kri->id,
            'reading_date' => now()->toDateString(),
            'value'        => $value,
            'status'       => $status,
            'notes'        => $notes,
            'is_auto'      => false,
            'recorded_by'  => $userId,
        ]);

        $kri->update([
            'last_measured_at' => now(),
            'last_value'       => $value,
            'last_status'      => $status,
        ]);

        if (in_array($status, [GrcKri::STATUS_AMBER, GrcKri::STATUS_RED], true)) {
            $this->dispatchKriAlert($kri, $value, $status);
        }

        return $reading;
    }

    /**
     * Compute green / amber / red based on the KRI's thresholds and direction.
     */
    public function computeStatus(GrcKri $kri, float $value): string
    {
        $green = (float) $kri->threshold_green;
        $amber = (float) $kri->threshold_amber;

        if ($kri->direction === GrcKri::DIRECTION_LOWER_BETTER) {
            // Lower is better: green < amber < red
            if ($value <= $green) {
                return GrcKri::STATUS_GREEN;
            }

            if ($value <= $amber) {
                return GrcKri::STATUS_AMBER;
            }

            return GrcKri::STATUS_RED;
        }

        // Higher is better: green > amber > red
        if ($value >= $green) {
            return GrcKri::STATUS_GREEN;
        }

        if ($value >= $amber) {
            return GrcKri::STATUS_AMBER;
        }

        return GrcKri::STATUS_RED;
    }

    /**
     * Return reading history for a KRI with trend direction.
     *
     * @return array{kri_code: string, readings: array, trend: string}
     */
    public function getReadings(int $organizationId, string $uuid, int $months = 6): array
    {
        $kri = GrcKri::where('organization_id', $organizationId)->where('uuid', $uuid)->firstOrFail();

        $readings = GrcKriReading::where('kri_id', $kri->id)
            ->where('reading_date', '>=', now()->subMonths($months)->toDateString())
            ->orderBy('reading_date')
            ->get(['reading_date', 'value', 'status', 'is_auto']);

        $trend = 'stable';

        if ($readings->count() >= 2) {
            $first = (float) $readings->first()->value;
            $last  = (float) $readings->last()->value;
            $delta = $last - $first;

            if (abs($delta) > 0.01) {
                $trend = $kri->direction === GrcKri::DIRECTION_LOWER_BETTER
                    ? ($delta > 0 ? 'worsening' : 'improving')
                    : ($delta > 0 ? 'improving' : 'worsening');
            }
        }

        return [
            'kri_code' => $kri->kri_code,
            'name'     => $kri->name,
            'readings' => $readings->toArray(),
            'trend'    => $trend,
        ];
    }

    /**
     * Run all active KRIs that are due for measurement.
     * For manual data sources this is a no-op; for known auto-sources it records a reading.
     *
     * @return array{kris_evaluated: int, readings_created: int, errors: int}
     */
    public function runAllDue(int $organizationId): array
    {
        $evaluated      = 0;
        $readingsCreated = 0;
        $errors         = 0;

        GrcKri::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->chunkById(100, function ($kris) use ($organizationId, &$evaluated, &$readingsCreated, &$errors) {
                foreach ($kris as $kri) {
                    if (!$kri->isDue()) {
                        continue;
                    }

                    $evaluated++;

                    if ($kri->data_source === 'manual') {
                        continue; // Manual KRIs require a human reading
                    }

                    try {
                        $value = $this->computeAutoValue($organizationId, $kri);

                        if ($value !== null) {
                            $status = $this->computeStatus($kri, $value);

                            GrcKriReading::create([
                                'kri_id'       => $kri->id,
                                'reading_date' => now()->toDateString(),
                                'value'        => $value,
                                'status'       => $status,
                                'notes'        => 'Auto-computed',
                                'is_auto'      => true,
                                'recorded_by'  => $kri->created_by,
                            ]);

                            $kri->update([
                                'last_measured_at' => now(),
                                'last_value'       => $value,
                                'last_status'      => $status,
                            ]);

                            if (in_array($status, [GrcKri::STATUS_AMBER, GrcKri::STATUS_RED], true)) {
                                $this->dispatchKriAlert($kri, $value, $status);
                            }

                            $readingsCreated++;
                        }
                    } catch (\Throwable $e) {
                        Log::error('KRI auto-run failed', [
                            'kri_uuid' => $kri->uuid,
                            'error'    => $e->getMessage(),
                        ]);
                        $errors++;
                    }
                }
            });

        return [
            'kris_evaluated'  => $evaluated,
            'readings_created' => $readingsCreated,
            'errors'          => $errors,
        ];
    }

    /**
     * Dispatch a notification to the KRI owner when the status is amber or red.
     */
    private function dispatchKriAlert(GrcKri $kri, float $value, string $status): void
    {
        $owner = $kri->owner;

        if ($owner === null) {
            return;
        }

        $statusLabel = strtoupper($status);
        $title       = "[KRI {$statusLabel}] {$kri->name}";
        $message     = "KRI '{$kri->name}' ({$kri->kri_code}) recorded a value of {$value}, "
            . "which is in the {$statusLabel} zone. Immediate review may be required.";

        try {
            $this->notificationService->send(
                user: $owner,
                type: "kri_{$status}_alert",
                title: $title,
                message: $message,
                notifiable: $kri,
                actionUrl: null,
                actionText: null,
                data: [
                    'kri_uuid'    => $kri->uuid,
                    'kri_code'    => $kri->kri_code,
                    'value'       => $value,
                    'status'      => $status,
                    'measured_at' => now()->toIso8601String(),
                ],
                channels: ['database'],
            );
        } catch (\Throwable $e) {
            Log::warning('KRI alert notification failed', [
                'kri_uuid' => $kri->uuid,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Compute an automatic metric value for supported data sources.
     * Returns null if not computable automatically.
     */
    private function computeAutoValue(int $organizationId, GrcKri $kri): ?float
    {
        $supportedSources = ['invoices', 'journal_entries', 'purchase_orders', 'bills', 'employees'];

        if (!in_array($kri->data_source, $supportedSources, true)) {
            return null;
        }

        $tableMap = [
            'invoices'        => 'invoices',
            'journal_entries' => 'journal_entries',
            'purchase_orders' => 'purchase_orders',
            'bills'           => 'bills',
            'employees'       => 'employees',
        ];

        $table = $tableMap[$kri->data_source] ?? null;

        if ($table === null) {
            return null;
        }

        $query = \Illuminate\Support\Facades\DB::table($table)
            ->where('organization_id', $organizationId);

        $field = $kri->metric_field ?: 'id';

        return match ($kri->aggregation) {
            GrcKri::AGGREGATION_COUNT => (float) $query->count(),
            GrcKri::AGGREGATION_SUM   => (float) $query->sum($field),
            GrcKri::AGGREGATION_AVG   => (float) $query->avg($field),
            GrcKri::AGGREGATION_MAX   => (float) $query->max($field),
            GrcKri::AGGREGATION_MIN   => (float) $query->min($field),
            default                   => null,
        };
    }
}
