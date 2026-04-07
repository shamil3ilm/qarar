<?php

declare(strict_types=1);

namespace App\Services\Fraud;

use App\Models\Fraud\FraudAlert;
use App\Models\Fraud\FraudRule;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FraudRuleEngine
{
    /**
     * Countries considered high-risk for geographic checks.
     */
    private const HIGH_RISK_COUNTRIES = ['IR', 'KP', 'SY', 'CU', 'SD', 'VE', 'MM'];

    /**
     * Severity ordering for comparison purposes.
     */
    private const SEVERITY_ORDER = [
        FraudRule::LOW      => 1,
        FraudRule::MEDIUM   => 2,
        FraudRule::HIGH     => 3,
        FraudRule::CRITICAL => 4,
    ];

    public function __construct(
        private readonly FraudAlertNotifier $alertNotifier,
    ) {}

    /**
     * Evaluate an entity against all active fraud rules for an organization.
     * This method NEVER throws — a check failure must not block a transaction.
     */
    public function evaluate(string $entityType, array $entityData, int $organizationId): EvaluationResult
    {
        try {
            return $this->runEvaluation($entityType, $entityData, $organizationId);
        } catch (\Throwable $e) {
            Log::error('FraudRuleEngine evaluation failed — returning safe non-flagged result', [
                'entity_type'     => $entityType,
                'organization_id' => $organizationId,
                'error'           => $e->getMessage(),
                'trace'           => $e->getTraceAsString(),
            ]);

            return EvaluationResult::noFlag();
        }
    }

    private function runEvaluation(string $entityType, array $entityData, int $organizationId): EvaluationResult
    {
        $rules = FraudRule::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where('entity_type', $entityType)
            ->get();

        $triggeredRules  = [];
        $totalScore      = 0;
        $shouldBlock     = false;
        $highestSeverity = FraudRule::LOW;

        foreach ($rules as $rule) {
            if (!$this->ruleMatches($rule, $entityData, $organizationId)) {
                continue;
            }

            $triggeredRules[] = [
                'rule_id'   => $rule->id,
                'rule_name' => $rule->name,
                'score'     => $rule->score_impact,
                'severity'  => $rule->severity,
            ];

            $totalScore += $rule->score_impact;

            if ($rule->auto_block) {
                $shouldBlock = true;
            }

            if ((self::SEVERITY_ORDER[$rule->severity] ?? 0) > (self::SEVERITY_ORDER[$highestSeverity] ?? 0)) {
                $highestSeverity = $rule->severity;
            }
        }

        $flagged = $totalScore > 0;

        if ($flagged) {
            $this->persistAlerts($triggeredRules, $entityType, $entityData, $organizationId, $highestSeverity, $totalScore);
        }

        return new EvaluationResult(
            flagged:         $flagged,
            totalScore:      $totalScore,
            triggeredRules:  $triggeredRules,
            highestSeverity: $highestSeverity,
            shouldBlock:     $shouldBlock,
        );
    }

    /**
     * Dispatch to the appropriate evaluator based on rule_type.
     */
    private function ruleMatches(FraudRule $rule, array $entityData, int $organizationId): bool
    {
        return match ($rule->rule_type) {
            FraudRule::VELOCITY   => $this->evaluateVelocity($rule, $entityData, $organizationId),
            FraudRule::AMOUNT     => $this->evaluateAmount($rule, $entityData),
            FraudRule::PATTERN    => $this->evaluatePattern($rule, $entityData, $organizationId),
            FraudRule::BEHAVIORAL => $this->evaluateBehavioral($rule, $entityData, $organizationId),
            FraudRule::GEOGRAPHIC => $this->evaluateGeographic($rule, $entityData),
            default               => false,
        };
    }

    // -------------------------------------------------------------------------
    // VELOCITY rules
    // conditions: {"metric": "invoice_count", "window_minutes": 60, "threshold": 10}
    // -------------------------------------------------------------------------
    private function evaluateVelocity(FraudRule $rule, array $entityData, int $organizationId): bool
    {
        $conditions    = $rule->conditions;
        $metric        = $conditions['metric'] ?? null;
        $windowMinutes = (int) ($conditions['window_minutes'] ?? 60);
        $threshold     = (int) ($conditions['threshold'] ?? 10);

        if ($metric === null) {
            return false;
        }

        $since = now()->subMinutes($windowMinutes);

        $count = match ($metric) {
            'invoice_count' => Invoice::withoutGlobalScope('organization')
                ->where('organization_id', $organizationId)
                ->where('created_at', '>=', $since)
                ->count(),

            'payment_count' => PaymentReceived::withoutGlobalScope('organization')
                ->where('organization_id', $organizationId)
                ->where('created_at', '>=', $since)
                ->count(),

            'login_count' => DB::table('user_events')
                ->where('organization_id', $organizationId)
                ->where('event_type', 'user_login')
                ->where('created_at', '>=', $since)
                ->count(),

            'failed_login_count' => DB::table('login_attempts')
                ->where('email', $entityData['email'] ?? '')
                ->where('successful', false)
                ->where('created_at', '>=', $since)
                ->count(),

            default => 0,
        };

        return $count > $threshold;
    }

    // -------------------------------------------------------------------------
    // AMOUNT rules
    // conditions: {"field": "total", "operator": ">=", "value": 50000}
    // -------------------------------------------------------------------------
    private function evaluateAmount(FraudRule $rule, array $entityData): bool
    {
        $conditions = $rule->conditions;
        $field      = $conditions['field'] ?? 'total';
        $operator   = $conditions['operator'] ?? '>=';
        $threshold  = (float) ($conditions['value'] ?? 0);

        $fieldValue = (float) ($entityData[$field] ?? 0);

        return match ($operator) {
            '>='    => $fieldValue >= $threshold,
            '>'     => $fieldValue > $threshold,
            '='     => $fieldValue === $threshold,
            '<='    => $fieldValue <= $threshold,
            '<'     => $fieldValue < $threshold,
            default => false,
        };
    }

    // -------------------------------------------------------------------------
    // PATTERN rules
    // conditions: {"pattern": "structuring"|"rapid_payment"|"round_amount", ...}
    // -------------------------------------------------------------------------
    private function evaluatePattern(FraudRule $rule, array $entityData, int $organizationId): bool
    {
        $conditions = $rule->conditions;
        $pattern    = $conditions['pattern'] ?? null;

        return match ($pattern) {
            'structuring'  => $this->detectStructuring($conditions, $entityData, $organizationId),
            'rapid_payment' => $this->detectRapidPayment($conditions, $entityData, $organizationId),
            'round_amount' => $this->detectRoundAmount($conditions, $entityData),
            default        => false,
        };
    }

    private function detectStructuring(array $conditions, array $entityData, int $organizationId): bool
    {
        $threshold  = (float) ($conditions['threshold'] ?? 9000);
        $windowDays = (int) ($conditions['window_days'] ?? 7);
        $contactId  = (int) ($entityData['contact_id'] ?? 0);

        if ($contactId === 0) {
            return false;
        }

        $since = now()->subDays($windowDays);

        // Count payments by the same contact that are between threshold-10% and threshold
        $lowerBound = $threshold * 0.8;

        $count = PaymentReceived::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('customer_id', $contactId)
            ->where('created_at', '>=', $since)
            ->whereBetween('amount', [$lowerBound, $threshold - 0.01])
            ->count();

        return $count >= 3;
    }

    private function detectRapidPayment(array $conditions, array $entityData, int $organizationId): bool
    {
        $minAmount = (float) ($conditions['min_amount'] ?? 0);
        $amount    = (float) ($entityData['amount'] ?? 0);
        $contactId = (int) ($entityData['contact_id'] ?? 0);

        if ($amount < $minAmount || $contactId === 0) {
            return false;
        }

        // Check if there is an invoice for this contact created within 1 hour before payment
        $oneHourAgo = now()->subHour();

        return Invoice::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('customer_id', $contactId)
            ->where('created_at', '>=', $oneHourAgo)
            ->exists();
    }

    private function detectRoundAmount(array $conditions, array $entityData): bool
    {
        $minAmount = (float) ($conditions['min_amount'] ?? 10000);
        $amount    = (float) ($entityData['amount'] ?? $entityData['total'] ?? 0);

        if ($amount < $minAmount) {
            return false;
        }

        // A round amount has no fractional part (e.g. 10000.00, 50000.00)
        return fmod($amount, 1000) === 0.0;
    }

    // -------------------------------------------------------------------------
    // BEHAVIORAL rules
    // conditions: {"behavior": "new_ip_login", ...}
    // -------------------------------------------------------------------------
    private function evaluateBehavioral(FraudRule $rule, array $entityData, int $organizationId): bool
    {
        $behavior = $rule->conditions['behavior'] ?? null;

        return match ($behavior) {
            'new_ip_login'                  => $this->detectNewIpLogin($entityData),
            'high_value_after_info_change'  => $this->detectHighValueAfterInfoChange($entityData, $organizationId),
            default                         => false,
        };
    }

    private function detectNewIpLogin(array $entityData): bool
    {
        $userId    = (int) ($entityData['user_id'] ?? 0);
        $ipAddress = $entityData['ip_address'] ?? null;

        if ($userId === 0 || $ipAddress === null) {
            return false;
        }

        $since = now()->subDays(30);

        // Check if this IP has been seen in the last 30 days for this user
        $seen = DB::table('user_events')
            ->where('user_id', $userId)
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', $since)
            ->exists();

        return !$seen;
    }

    private function detectHighValueAfterInfoChange(array $entityData, int $organizationId): bool
    {
        $userId    = (int) ($entityData['user_id'] ?? 0);
        $amount    = (float) ($entityData['amount'] ?? $entityData['total'] ?? 0);
        $threshold = 10000.0;

        if ($userId === 0 || $amount < $threshold) {
            return false;
        }

        // Check if user profile was updated in last 24 hours
        $since = now()->subHours(24);

        return DB::table('audit_logs')
            ->where('user_id', $userId)
            ->where('auditable_type', 'App\\Models\\User')
            ->where('event', 'updated')
            ->where('created_at', '>=', $since)
            ->exists();
    }

    // -------------------------------------------------------------------------
    // GEOGRAPHIC rules
    // conditions: {"allowed_countries": ["SA", "AE"], "block_high_risk": true}
    // -------------------------------------------------------------------------
    private function evaluateGeographic(FraudRule $rule, array $entityData): bool
    {
        $conditions      = $rule->conditions;
        $allowedCountries = $conditions['allowed_countries'] ?? null;
        $blockHighRisk   = (bool) ($conditions['block_high_risk'] ?? false);

        $country = $entityData['country_code'] ?? $entityData['billing_country_code'] ?? null;

        if ($country === null) {
            return false;
        }

        // Behavior: new_country_login
        $behavior = $conditions['behavior'] ?? null;
        if ($behavior === 'new_country_login') {
            return in_array($country, self::HIGH_RISK_COUNTRIES, true);
        }

        if ($blockHighRisk && in_array($country, self::HIGH_RISK_COUNTRIES, true)) {
            return true;
        }

        if ($allowedCountries !== null && !in_array($country, $allowedCountries, true)) {
            return true;
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Alert persistence
    // -------------------------------------------------------------------------
    private function persistAlerts(
        array  $triggeredRules,
        string $entityType,
        array  $entityData,
        int    $organizationId,
        string $highestSeverity,
        int    $totalScore
    ): void {
        foreach ($triggeredRules as $triggered) {
            try {
                /** @var FraudAlert $alert */
                $alert = FraudAlert::create([
                    'organization_id' => $organizationId,
                    'fraud_rule_id'   => $triggered['rule_id'],
                    'entity_type'     => $entityType,
                    'entity_id'       => (int) ($entityData['id'] ?? 0),
                    'entity_uuid'     => $entityData['uuid'] ?? null,
                    'user_id'         => $entityData['user_id'] ?? null,
                    'contact_id'      => $entityData['contact_id'] ?? null,
                    'severity'        => $triggered['severity'],
                    'status'          => FraudAlert::OPEN,
                    'fraud_score'     => $triggered['score'],
                    'evidence'        => array_filter([
                        'entity_data'      => $entityData,
                        'total_score'      => $totalScore,
                        'highest_severity' => $highestSeverity,
                    ]),
                    'ip_address'      => $entityData['ip_address'] ?? null,
                ]);

                // Notify admins for HIGH or CRITICAL alerts
                if ((self::SEVERITY_ORDER[$triggered['severity']] ?? 0) >= self::SEVERITY_ORDER[FraudRule::HIGH]) {
                    try {
                        $this->alertNotifier->notifyAdmins($alert);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to notify admins of fraud alert', [
                            'alert_id' => $alert->id,
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Failed to persist fraud alert', [
                    'rule_id' => $triggered['rule_id'],
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }
}
