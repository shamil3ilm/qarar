<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Granular API activity tracking — what endpoint was called, when, how long it took
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);           // GET, POST, PUT, DELETE, PATCH
            $table->string('route_name')->nullable(); // Named route (e.g. sales.invoices.store)
            $table->string('module')->nullable();    // Resolved module (sales, hr, inventory...)
            $table->string('action')->nullable();    // store, index, show, update, destroy
            $table->string('entity_type')->nullable(); // Invoice, Employee, etc.
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedSmallInteger('response_status'); // HTTP status code
            $table->unsignedInteger('duration_ms')->nullable(); // Request duration
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_type')->nullable(); // mobile, tablet, desktop
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->json('request_summary')->nullable(); // non-sensitive subset of request params
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['organization_id', 'module', 'created_at']);
            $table->index(['route_name', 'created_at']);
        });

        // Session tracking — link activity logs to sessions with duration
        Schema::create('user_sessions_extended', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('session_token_hash', 64); // hashed JWT jti claim
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_type')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('city')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable(); // computed on logout
            $table->unsignedInteger('request_count')->default(0);
            $table->json('modules_accessed')->nullable(); // set of module names visited
            $table->string('end_reason')->nullable(); // logout, timeout, token_blacklisted
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'started_at']);
            $table->index(['organization_id', 'started_at']);
        });

        // Feature usage aggregates — pre-computed for clustering and analytics
        Schema::create('user_feature_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('module');               // sales, hr, inventory, etc.
            $table->string('feature');              // invoices, employees, stock_transfer, etc.
            $table->date('usage_date');
            $table->unsignedInteger('access_count')->default(0);
            $table->unsignedInteger('create_count')->default(0);
            $table->unsignedInteger('update_count')->default(0);
            $table->unsignedInteger('delete_count')->default(0);
            $table->unsignedInteger('total_duration_ms')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_id', 'module', 'feature', 'usage_date']);
            $table->index(['organization_id', 'module', 'usage_date']);
        });

        // Clustering snapshots — computed cluster assignments for users
        Schema::create('user_cluster_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('cluster_name');         // high_value, inactive, power_user, etc.
            $table->string('algorithm')->default('rule_based'); // rule_based, kmeans (future)
            $table->unsignedTinyInteger('confidence')->default(100); // 0-100
            $table->json('dimensions');             // dimension values used for assignment
            $table->timestamp('assigned_at');
            $table->timestamp('expires_at')->nullable(); // re-evaluate after this date
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'cluster_name']);
            $table->index(['organization_id', 'cluster_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_cluster_assignments');
        Schema::dropIfExists('user_feature_usage');
        Schema::dropIfExists('user_sessions_extended');
        Schema::dropIfExists('user_activity_logs');
    }
};
