<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Core\Organization;
use App\Models\Fraud\FraudRule;
use App\Services\Fraud\FraudRuleTemplates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class DefaultFraudRulesSeeder extends Seeder
{
    /**
     * Seed default fraud rules for all existing organizations.
     * For new organizations, call FraudRuleTemplates::defaults() during org creation.
     */
    public function run(): void
    {
        $templates = FraudRuleTemplates::defaults();

        Organization::all()->each(function (Organization $org) use ($templates): void {
            $this->seedForOrganization($org, $templates);
        });

        $this->command->info('Default fraud rules seeded for all organizations.');
    }

    /**
     * Seed default rules for a specific organization (idempotent — skips existing rule names).
     */
    public function seedForOrganization(Organization $org, ?array $templates = null): void
    {
        $templates ??= FraudRuleTemplates::defaults();

        // Find the first admin/owner user for created_by (fallback to user id 1)
        $adminUser = \App\Models\User::where('organization_id', $org->id)
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
            ->first();

        $createdBy = $adminUser?->id ?? 1;

        foreach ($templates as $template) {
            $exists = FraudRule::withoutGlobalScope('organization')
                ->where('organization_id', $org->id)
                ->where('name', $template['name'])
                ->exists();

            if (!$exists) {
                try {
                    FraudRule::withoutGlobalScope('organization')->create(array_merge($template, [
                        'organization_id' => $org->id,
                        'created_by'      => $createdBy,
                    ]));
                } catch (\Throwable $e) {
                    Log::warning('DefaultFraudRulesSeeder: failed to create rule', [
                        'organization_id' => $org->id,
                        'rule_name'       => $template['name'],
                        'error'           => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
