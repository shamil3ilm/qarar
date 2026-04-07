<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add lifecycle columns to users table
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('last_login_at');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->foreignId('deactivated_by')->nullable()->after('deactivated_at')
                ->constrained('users')->nullOnDelete();
            $table->string('deactivation_reason', 255)->nullable()->after('deactivated_by');
            $table->timestamp('roles_updated_at')->nullable()->after('deactivation_reason');
        });

        // User invitations for onboarding
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->string('name', 100)->nullable();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Created user
            $table->timestamps();

            $table->unique(['organization_id', 'email']);
            $table->index(['email', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['deactivated_by']);
            $table->dropColumn([
                'onboarding_completed_at',
                'deactivated_at',
                'deactivated_by',
                'deactivation_reason',
                'roles_updated_at',
            ]);
        });
    }
};
