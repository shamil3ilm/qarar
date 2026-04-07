<?php

declare(strict_types=1);

namespace App\Services\Fraud;

use App\Models\Fraud\FraudRule;

class FraudRuleTemplates
{
    /**
     * Return default fraud rule definitions to seed into a new organization.
     * The calling code is responsible for filling in organization_id and created_by.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            [
                'name'         => 'High velocity invoices',
                'rule_type'    => FraudRule::VELOCITY,
                'entity_type'  => 'invoice',
                'conditions'   => [
                    'metric'         => 'invoice_count',
                    'window_minutes' => 60,
                    'threshold'      => 20,
                ],
                'severity'     => FraudRule::HIGH,
                'is_active'    => true,
                'auto_block'   => false,
                'score_impact' => 30,
            ],
            [
                'name'         => 'Large single transaction',
                'rule_type'    => FraudRule::AMOUNT,
                'entity_type'  => 'invoice',
                'conditions'   => [
                    'field'    => 'total',
                    'operator' => '>=',
                    'value'    => 50000,
                ],
                'severity'     => FraudRule::MEDIUM,
                'is_active'    => true,
                'auto_block'   => false,
                'score_impact' => 20,
            ],
            [
                'name'         => 'Structuring pattern',
                'rule_type'    => FraudRule::PATTERN,
                'entity_type'  => 'payment',
                'conditions'   => [
                    'pattern'     => 'structuring',
                    'threshold'   => 9000,
                    'window_days' => 7,
                ],
                'severity'     => FraudRule::CRITICAL,
                'is_active'    => true,
                'auto_block'   => false,
                'score_impact' => 50,
            ],
            [
                'name'         => 'Login from new country',
                'rule_type'    => FraudRule::GEOGRAPHIC,
                'entity_type'  => 'login',
                'conditions'   => [
                    'behavior' => 'new_country_login',
                ],
                'severity'     => FraudRule::MEDIUM,
                'is_active'    => true,
                'auto_block'   => false,
                'score_impact' => 15,
            ],
            [
                'name'         => 'Multiple failed logins',
                'rule_type'    => FraudRule::VELOCITY,
                'entity_type'  => 'login',
                'conditions'   => [
                    'metric'         => 'failed_login_count',
                    'window_minutes' => 15,
                    'threshold'      => 5,
                ],
                'severity'     => FraudRule::HIGH,
                'is_active'    => true,
                'auto_block'   => false,
                'score_impact' => 25,
            ],
            [
                'name'         => 'Round-amount transaction',
                'rule_type'    => FraudRule::PATTERN,
                'entity_type'  => 'payment',
                'conditions'   => [
                    'pattern'    => 'round_amount',
                    'min_amount' => 10000,
                ],
                'severity'     => FraudRule::LOW,
                'is_active'    => true,
                'auto_block'   => false,
                'score_impact' => 10,
            ],
        ];
    }
}
