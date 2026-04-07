<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\Automation\AutomationRule;
use App\Models\Automation\AutomationRuleLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomationRuleService
{
    /**
     * Create a new automation rule.
     */
    public function create(array $data, int $userId): AutomationRule
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['is_active'] = $data['is_active'] ?? true;
            $data['priority'] = $data['priority'] ?? 0;
            $data['execution_count'] = 0;
            $data['created_by'] = $data['created_by'] ?? $userId;

            $rule = AutomationRule::create($data);

            // If it's a scheduled rule, create the initial schedule
            if ($rule->isScheduled() && !empty($rule->trigger_schedule)) {
                app(AutomationScheduleService::class)->create($rule);
            }

            return $rule;
        });
    }

    /**
     * Update an existing automation rule.
     */
    public function update(AutomationRule $rule, array $data): AutomationRule
    {
        return DB::transaction(function () use ($rule, $data) {
            $rule->update($data);

            // If the schedule changed, update scheduled jobs
            if (isset($data['trigger_schedule']) && $rule->isScheduled()) {
                $rule->schedules()->pending()->delete();
                app(AutomationScheduleService::class)->create($rule);
            }

            return $rule->fresh();
        });
    }

    /**
     * Activate an automation rule.
     */
    public function activate(AutomationRule $rule): AutomationRule
    {
        return DB::transaction(function () use ($rule) {
            $rule->update(['is_active' => true]);

            // If it's a scheduled rule, create the next schedule
            if ($rule->isScheduled()) {
                app(AutomationScheduleService::class)->create($rule);
            }

            return $rule->fresh();
        });
    }

    /**
     * Deactivate an automation rule.
     */
    public function deactivate(AutomationRule $rule): AutomationRule
    {
        return DB::transaction(function () use ($rule) {
            $rule->update(['is_active' => false]);

            // Cancel any pending schedules
            $rule->schedules()->pending()->delete();

            return $rule->fresh();
        });
    }

    /**
     * Evaluate rule conditions against an entity.
     */
    public function evaluate(AutomationRule $rule, Model $entity): bool
    {
        $conditions = $rule->conditions;

        if (empty($conditions)) {
            return true;
        }

        return $this->evaluateConditionGroups($conditions, $entity);
    }

    /**
     * Execute the actions defined in the rule.
     */
    public function executeActions(AutomationRule $rule, Model $entity): array
    {
        $startTime = microtime(true);
        $actionsExecuted = [];
        $error = null;

        try {
            $actions = $rule->actions;

            foreach ($actions as $action) {
                $result = $this->executeSingleAction($action, $entity, $rule);
                $actionsExecuted[] = [
                    'type' => $action['type'] ?? 'unknown',
                    'result' => $result,
                    'executed_at' => now()->toISOString(),
                ];
            }

            $rule->incrementExecutionCount();

            $this->logExecution(
                $rule,
                $entity,
                AutomationRuleLog::STATUS_SUCCESS,
                $rule->conditions,
                $actionsExecuted,
                null,
                $this->getExecutionTimeMs($startTime)
            );

            return $actionsExecuted;
        } catch (\Throwable $e) {
            $error = $e->getMessage();

            Log::error('Automation rule execution failed', [
                'rule_id' => $rule->id,
                'entity_type' => get_class($entity),
                'entity_id' => $entity->getKey(),
                'error' => $error,
            ]);

            $this->logExecution(
                $rule,
                $entity,
                AutomationRuleLog::STATUS_FAILED,
                $rule->conditions,
                $actionsExecuted,
                $error,
                $this->getExecutionTimeMs($startTime)
            );

            throw $e;
        }
    }

    /**
     * Log the execution of an automation rule.
     */
    public function logExecution(
        AutomationRule $rule,
        Model $entity,
        string $status,
        ?array $conditionsMatched = null,
        ?array $actionsExecuted = null,
        ?string $errorMessage = null,
        ?int $executionTimeMs = null
    ): AutomationRuleLog {
        return AutomationRuleLog::create([
            'rule_id' => $rule->id,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->getKey(),
            'status' => $status,
            'conditions_matched' => $conditionsMatched,
            'actions_executed' => $actionsExecuted,
            'error_message' => $errorMessage,
            'execution_time_ms' => $executionTimeMs,
        ]);
    }

    /**
     * Evaluate condition groups (supports AND/OR logic).
     */
    protected function evaluateConditionGroups(array $conditions, Model $entity): bool
    {
        // Top level is implicitly AND
        foreach ($conditions as $conditionGroup) {
            $operator = $conditionGroup['operator'] ?? 'and';
            $rules = $conditionGroup['rules'] ?? [$conditionGroup];

            if ($operator === 'or') {
                $groupResult = false;
                foreach ($rules as $condition) {
                    if ($this->evaluateSingleCondition($condition, $entity)) {
                        $groupResult = true;
                        break;
                    }
                }
            } else {
                $groupResult = true;
                foreach ($rules as $condition) {
                    if (!$this->evaluateSingleCondition($condition, $entity)) {
                        $groupResult = false;
                        break;
                    }
                }
            }

            if (!$groupResult) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition against the entity.
     */
    protected function evaluateSingleCondition(array $condition, Model $entity): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return false;
        }

        $entityValue = data_get($entity, $field);

        return match ($operator) {
            '=', 'equals' => $entityValue == $value,
            '!=', 'not_equals' => $entityValue != $value,
            '>', 'greater_than' => $entityValue > $value,
            '>=', 'greater_than_or_equal' => $entityValue >= $value,
            '<', 'less_than' => $entityValue < $value,
            '<=', 'less_than_or_equal' => $entityValue <= $value,
            'contains' => is_string($entityValue) && str_contains($entityValue, (string) $value),
            'not_contains' => is_string($entityValue) && !str_contains($entityValue, (string) $value),
            'starts_with' => is_string($entityValue) && str_starts_with($entityValue, (string) $value),
            'ends_with' => is_string($entityValue) && str_ends_with($entityValue, (string) $value),
            'in' => is_array($value) && in_array($entityValue, $value),
            'not_in' => is_array($value) && !in_array($entityValue, $value),
            'is_null' => is_null($entityValue),
            'is_not_null' => !is_null($entityValue),
            'is_empty' => empty($entityValue),
            'is_not_empty' => !empty($entityValue),
            default => false,
        };
    }

    /**
     * Execute a single action.
     */
    protected function executeSingleAction(array $action, Model $entity, AutomationRule $rule): array
    {
        $type = $action['type'] ?? null;

        return match ($type) {
            'update_field' => $this->executeUpdateFieldAction($action, $entity),
            'send_email' => $this->executeSendEmailAction($action, $entity, $rule),
            'send_notification' => $this->executeSendNotificationAction($action, $entity, $rule),
            'create_task' => $this->executeCreateTaskAction($action, $entity, $rule),
            'webhook' => $this->executeWebhookAction($action, $entity, $rule),
            default => ['status' => 'skipped', 'reason' => "Unknown action type: {$type}"],
        };
    }

    /**
     * Update a field on the entity.
     */
    protected function executeUpdateFieldAction(array $action, Model $entity): array
    {
        $field = $action['field'] ?? null;
        $value = $action['value'] ?? null;

        if (!$field) {
            return ['status' => 'failed', 'reason' => 'No field specified'];
        }

        $entity->update([$field => $value]);

        return ['status' => 'success', 'field' => $field, 'value' => $value];
    }

    /**
     * Send an email action.
     */
    protected function executeSendEmailAction(array $action, Model $entity, AutomationRule $rule): array
    {
        $templateId = $action['template_id'] ?? null;
        $recipient = $action['recipient'] ?? null;

        // Resolve recipient from entity if not specified
        if (!$recipient && method_exists($entity, 'getEmailAddress')) {
            $recipient = $entity->getEmailAddress();
        }

        if (!$recipient) {
            $recipient = data_get($entity, 'email');
        }

        if (!$recipient) {
            return ['status' => 'skipped', 'reason' => 'No recipient email found'];
        }

        // Queue email for sending (actual sending handled by email service)
        return [
            'status' => 'queued',
            'template_id' => $templateId,
            'recipient' => $recipient,
        ];
    }

    /**
     * Send a notification action.
     */
    protected function executeSendNotificationAction(array $action, Model $entity, AutomationRule $rule): array
    {
        $userId = $action['user_id'] ?? $rule->created_by;
        $message = $action['message'] ?? 'Automation rule triggered';

        return [
            'status' => 'queued',
            'user_id' => $userId,
            'message' => $message,
        ];
    }

    /**
     * Create a task action.
     */
    protected function executeCreateTaskAction(array $action, Model $entity, AutomationRule $rule): array
    {
        return [
            'status' => 'created',
            'title' => $action['title'] ?? 'Automation Task',
            'assigned_to' => $action['assigned_to'] ?? $rule->created_by,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->getKey(),
        ];
    }

    /**
     * Execute a webhook action.
     */
    protected function executeWebhookAction(array $action, Model $entity, AutomationRule $rule): array
    {
        $url = $action['url'] ?? null;

        if (!$url) {
            return ['status' => 'failed', 'reason' => 'No webhook URL specified'];
        }

        return [
            'status' => 'queued',
            'url' => $url,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->getKey(),
        ];
    }

    /**
     * Get execution time in milliseconds.
     */
    protected function getExecutionTimeMs(float $startTime): int
    {
        return (int) round((microtime(true) - $startTime) * 1000);
    }
}
