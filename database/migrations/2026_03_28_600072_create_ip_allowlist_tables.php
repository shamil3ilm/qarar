<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ip_access_logs');
        Schema::dropIfExists('ip_allowlist_rules');

        Schema::create('ip_allowlist_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('rule_name');
            $table->string('ip_address')->nullable();
            $table->string('ip_range_start')->nullable();
            $table->string('ip_range_end')->nullable();
            $table->string('cidr_notation')->nullable();
            $table->enum('rule_type', ['allow', 'deny'])->default('allow');
            $table->enum('applies_to', ['all', 'api', 'admin', 'specific_role'])->default('all');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('role_id', 'ip_allow_role_fk')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('created_by', 'ip_allow_usr_fk')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('ip_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address');
            $table->enum('action', ['allowed', 'denied', 'challenged']);
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->string('endpoint')->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id', 'ip_log_usr_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rule_id', 'ip_log_rule_fk')->references('id')->on('ip_allowlist_rules')->nullOnDelete();
            $table->index(['organization_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_access_logs');
        Schema::dropIfExists('ip_allowlist_rules');
    }
};
