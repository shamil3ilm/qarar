<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EVM Baseline — SAP PS project baseline.
 *
 * A baseline freezes a project's planned cost and schedule at a point in time.
 * Multiple versions are supported (Original, Revised, Current).
 * EVM metrics (SPI, CPI, etc.) are measured against the active baseline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_evm_baselines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->string('name');                                 // Original Baseline, Re-baseline 1, etc.
            $table->string('baseline_type')->default('original');   // original|revised|current
            $table->date('baseline_date');
            $table->decimal('planned_cost', 15, 2);                // BAC at baseline
            $table->decimal('planned_duration_days', 10, 1);
            $table->date('planned_start');
            $table->date('planned_finish');
            $table->boolean('is_active')->default(false);          // only one active per project
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'is_active']);
            $table->index(['organization_id', 'project_id']);
        });

        // WBS-level baseline lines for granular tracking
        Schema::create('project_evm_baseline_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('baseline_id');
            $table->unsignedBigInteger('wbs_id');
            $table->decimal('planned_cost', 15, 2)->default(0);
            $table->date('planned_start');
            $table->date('planned_finish');
            $table->decimal('planned_duration_days', 10, 1)->default(0);
            $table->timestamps();

            $table->foreign('baseline_id')->references('id')->on('project_evm_baselines')->cascadeOnDelete();
            $table->index(['baseline_id', 'wbs_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_evm_baseline_lines');
        Schema::dropIfExists('project_evm_baselines');
    }
};
