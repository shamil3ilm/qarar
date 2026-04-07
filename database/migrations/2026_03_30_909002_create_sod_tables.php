<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grc_sod_functions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('function_code', 50);
            $table->string('name', 150);
            $table->string('module', 50);
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'function_code']);
        });

        Schema::create('grc_sod_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('function_a_id')->constrained('grc_sod_functions')->restrictOnDelete();
            $table->foreignId('function_b_id')->constrained('grc_sod_functions')->restrictOnDelete();
            $table->enum('risk_level', ['critical', 'high', 'medium', 'low'])->default('high');
            $table->text('description')->nullable();
            $table->text('mitigation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'function_a_id', 'function_b_id'], 'grc_sod_conflicts_org_func_a_func_b_uniq');
            $table->index(['organization_id']);
        });

        Schema::create('grc_sod_violations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('conflict_id')->constrained('grc_sod_conflicts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['open', 'risk_accepted', 'mitigated', 'remediated'])->default('open');
            $table->text('mitigation_description')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->date('review_date')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();
            $table->index(['organization_id', 'user_id']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grc_sod_violations');
        Schema::dropIfExists('grc_sod_conflicts');
        Schema::dropIfExists('grc_sod_functions');
    }
};
