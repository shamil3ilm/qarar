<?php

declare(strict_types=1);

namespace App\Services\Campaign;

class SegmentTemplates
{
    /**
     * Return predefined segment condition templates that can be used as a starting
     * point when creating new segments in an organization.
     *
     * @return array<int, array{name: string, conditions: array}>
     */
    public static function templates(): array
    {
        return [
            [
                'name'       => 'Inactive Users (30+ days)',
                'conditions' => [
                    ['field' => 'last_login_days_ago', 'operator' => '>=', 'value' => 30],
                ],
            ],
            [
                'name'       => 'New Users (last 7 days)',
                'conditions' => [
                    ['field' => 'account_age_days', 'operator' => '<=', 'value' => 7],
                ],
            ],
            [
                'name'       => 'Power Users (10+ invoices)',
                'conditions' => [
                    ['field' => 'total_invoices', 'operator' => '>=', 'value' => 10],
                ],
            ],
            [
                'name'       => 'Users with Phone',
                'conditions' => [
                    ['field' => 'has_phone', 'operator' => '=', 'value' => 1],
                ],
            ],
        ];
    }
}
