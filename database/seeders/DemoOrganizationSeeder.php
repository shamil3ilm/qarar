<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\Core\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds one demo organization (Saudi/VAT) with a default branch and all core
 * modules enabled, then attaches the admin@admin.com account to it.
 *
 * This gives the admin a real tenant context so org-scoped pages (contacts,
 * invoices, etc.) read and write data. The tenant global scope filters every
 * query by the authenticated user's organization_id with NO super-admin
 * bypass (see App\Models\Concerns\BelongsToOrganization), so a super-admin
 * with a null organization sees nothing and cannot create records — hence the
 * membership below.
 *
 * Idempotent: keyed on the organization slug / branch code / module code and
 * the pivot existence, so it can be re-run safely.
 *
 * Run after AdminUserSeeder (which creates admin@admin.com).
 */
class DemoOrganizationSeeder extends Seeder
{
    private const ORG_SLUG = 'masaar-demo';
    private const COUNTRY = 'SA';

    public function run(): void
    {
        $org = Organization::withoutGlobalScopes()->updateOrCreate(
            ['slug' => self::ORG_SLUG],
            [
                'name'          => 'Masaar Demo Co.',
                'country_code'  => self::COUNTRY,
                'tax_scheme'    => 'VAT',
                'base_currency' => 'SAR',
                'email'         => 'admin@admin.com',
                'is_active'     => true,
                'activated_at'  => now(),
            ],
        );

        $branch = Branch::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('code', 'HO')
            ->first();

        if (!$branch) {
            $branch = new Branch();
            $branch->uuid = (string) Str::uuid();
            $branch->organization_id = $org->id;
            $branch->name = 'Head Office';
            $branch->code = 'HO';
            $branch->country_code = self::COUNTRY;
            $branch->is_default = true;
            $branch->is_active = true;
            $branch->saveQuietly(); // skip audit — no authenticated user during seeding
        }

        $modules = ['core', 'accounting', 'inventory', 'sales', 'purchase', 'hr', 'crm', 'manufacturing'];
        foreach ($modules as $moduleCode) {
            OrganizationModule::updateOrCreate(
                ['organization_id' => $org->id, 'module_code' => $moduleCode],
                ['is_enabled' => true, 'enabled_at' => now()],
            );
        }

        // Attach the admin account to this organization.
        $user = User::withTrashed()->where('email', 'admin@admin.com')->first();
        if (!$user) {
            $this->command?->warn('admin@admin.com not found — run AdminUserSeeder first. Skipping membership.');
            return;
        }

        $user->organization_id = $org->id;
        $user->save();

        if (!$user->branches()->where('branches.id', $branch->id)->exists()) {
            $user->branches()->attach($branch->id, ['is_default' => true]);
        }

        // Attach the org admin role if one has been seeded (optional — super
        // admins bypass permission checks regardless).
        $adminRole = Role::withoutGlobalScopes()
            ->where('slug', 'admin')
            ->whereNull('organization_id')
            ->first();

        if ($adminRole && !$user->roles()->where('roles.id', $adminRole->id)->exists()) {
            $user->roles()->attach($adminRole->id);
        }
    }
}
