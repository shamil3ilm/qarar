<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds a universal super-admin account that can sign in to every app
 * (staff / admin / portal). Super admins bypass all permission, module
 * and organization checks, so the account needs no organization.
 *
 * Idempotent: keyed on the email, so running it again updates the existing
 * record (e.g. to reset the password) instead of creating a duplicate.
 *
 * Login: admin@admin.com  /  admin123
 *
 * NOTE: `is_super_admin` is intentionally excluded from the User model's
 * $fillable to prevent mass-assignment privilege escalation, so it is set
 * explicitly here rather than passed to updateOrCreate().
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::withTrashed()->updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name'              => 'Administrator',
                'password'          => 'admin123', // hashed automatically via the model's 'hashed' cast
                'is_active'         => true,
                'email_verified_at' => now(),
                'organization_id'   => null,
            ],
        );

        // Set privileged / non-fillable fields directly, then persist.
        $user->is_super_admin = true;
        $user->deleted_at = null; // un-trash if it was previously soft-deleted
        $user->save();
    }
}
