<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\Automation\AutomationRule;
use App\Models\Automation\AutomationSchedule;
use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomationScheduleService
{
    public function __construct(
        private AutomationRuleService $ruleService
    ) {}

    /**
     * Create a new schedule entry for a rule.
     */
    public function create(AutomationRule $rule): ?AutomationSchedule
    {
        if (!$rule->isScheduled() || empty($rule->trigger_schedule)) {
            return null;
        }

        $nextRun = $this->getNextRun($rule->trigger_schedule);

        if (!$nextRun) {
            return null;
        }

        return AutomationSchedule::create([
            'rule_id' => $rule->id,
            'scheduled_for' => $nextRun,
            'status' => AutomationSchedule::STATUS_PENDING,
        ]);
    }

    /**
     * Process all scheduled rules that are due.
     */
    public function processScheduledRules(): array
    {
        $results = [];

        $dueSchedules = AutomationSchedule::with('rule')
            ->due()
            ->get();

        foreach ($dueSchedules as $schedule) {
            $result = $this->processSchedule($schedule);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Process a single scheduled rule.
     */
    protected function processSchedule(AutomationSchedule $schedule): array
    {
        $rule = $schedule->rule;

        if (!$rule || !$rule->isActive()) {
            $schedule->update(['status' => AutomationSchedule::STATUS_FAILED]);
            return [
                'schedule_id' => $schedule->id,
                'rule_id' => $rule?->id,
                'status' => 'skipped',
                'reason' => 'Rule not active or not found',
            ];
        }

        try {
            DB::transaction(function () use ($schedule, $rule) {
                $schedule->markAsRunning();

                // Execute the rule's actions without entity context for scheduled rules
                // Scheduled rules typically operate on a query set
                $this->executeScheduledRule($rule);

                $schedule->markAsCompleted();

                // Create the next schedule entry
                $this->create($rule);
            });

            return [
                'schedule_id' => $schedule->id,
                'rule_id' => $rule->id,
                'status' => 'completed',
            ];
        } catch (\Throwable $e) {
            Log::error('Scheduled automation failed', [
                'schedule_id' => $schedule->id,
                'rule_id' => $rule->id,
                'error' => $e->getMessage(),
            ]);

            $schedule->markAsFailed();

            // Still create the next schedule even if this one failed
            $this->create($rule);

            return [
                'schedule_id' => $schedule->id,
                'rule_id' => $rule->id,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute a scheduled rule against its entity query set.
     */
    protected function executeScheduledRule(AutomationRule $rule): void
    {
        $entityClass = $this->resolveEntityClass($rule->entity_type);

        if (!$entityClass || !class_exists($entityClass)) {
            Log::warning("Unknown entity type for automation rule", [
                'rule_id' => $rule->id,
                'entity_type' => $rule->entity_type,
            ]);
            return;
        }

        $entities = $entityClass::query()
            ->where('organization_id', $rule->organization_id)
            ->get();

        foreach ($entities as $entity) {
            if ($this->ruleService->evaluate($rule, $entity)) {
                try {
                    $this->ruleService->executeActions($rule, $entity);
                } catch (\Throwable $e) {
                    Log::warning("Scheduled rule action failed for entity", [
                        'rule_id' => $rule->id,
                        'entity_id' => $entity->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Calculate the next run time from a cron expression.
     */
    public function getNextRun(string $cronExpression, ?Carbon $from = null): ?Carbon
    {
        try {
            $cron = new CronExpression($cronExpression);
            $nextRun = Carbon::instance($cron->getNextRunDate($from?->toDateTime() ?? 'now'));
            return $nextRun;
        } catch (\Throwable $e) {
            Log::warning('Invalid cron expression', [
                'expression' => $cronExpression,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Resolve entity class from entity type string.
     */
    protected function resolveEntityClass(string $entityType): ?string
    {
        $map = [
            'invoice' => \App\Models\Sales\Invoice::class,
            'customer' => \App\Models\Sales\Contact::class,
            'expense' => null,
            'payment' => \App\Models\Sales\PaymentReceived::class,
            'quotation' => \App\Models\Sales\Quotation::class,
            'purchase_order' => \App\Models\Purchase\PurchaseOrder::class,
            'bill' => \App\Models\Purchase\Bill::class,
            'lead' => \App\Models\CRM\Lead::class,
            'opportunity' => \App\Models\CRM\Opportunity::class,
            'employee' => \App\Models\HR\Employee::class,
        ];

        return $map[$entityType] ?? null;
    }
}
