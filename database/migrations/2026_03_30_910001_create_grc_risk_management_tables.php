<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // grc_risk_categories — risk taxonomy
        Schema::create('grc_risk_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->enum('risk_type', ['strategic', 'operational', 'financial', 'compliance', 'reputational', 'it', 'ehs']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'parent_id']);
        });

        // grc_risks — the risk register
        Schema::create('grc_risks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('risk_number', 30)->unique();
            $table->string('title', 200);
            $table->text('description');
            $table->foreignId('category_id')->nullable()->constrained('grc_risk_categories')->nullOnDelete();
            $table->enum('risk_type', ['strategic', 'operational', 'financial', 'compliance', 'reputational', 'it', 'ehs'])->default('operational');

            // Inherent risk (before controls)
            $table->unsignedTinyInteger('inherent_likelihood')->default(3); // 1-5
            $table->unsignedTinyInteger('inherent_impact')->default(3);     // 1-5
            $table->unsignedTinyInteger('inherent_score')->default(9);      // computed manually: likelihood * impact

            // Residual risk (after controls)
            $table->unsignedTinyInteger('residual_likelihood')->default(3);
            $table->unsignedTinyInteger('residual_impact')->default(3);
            $table->unsignedTinyInteger('residual_score')->default(9);      // computed manually: likelihood * impact

            $table->enum('risk_status', ['identified', 'assessed', 'treated', 'monitored', 'closed', 'accepted'])->default('identified');
            $table->foreignId('risk_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module_reference', 50)->nullable(); // Accounting, Sales, HR, etc.
            $table->text('existing_controls')->nullable();
            $table->date('next_review_date')->nullable();
            $table->date('identified_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'risk_status']);
            $table->index(['organization_id', 'risk_type']);
        });

        // grc_risk_treatments — treatment plans per risk
        Schema::create('grc_risk_treatments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('risk_id')->constrained('grc_risks')->cascadeOnDelete();
            $table->enum('treatment_type', ['avoid', 'reduce', 'transfer', 'accept']);
            $table->text('description');
            $table->text('action_plan')->nullable();
            $table->date('target_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            // Target residual risk after treatment
            $table->unsignedTinyInteger('target_likelihood')->nullable();
            $table->unsignedTinyInteger('target_impact')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'risk_id', 'status']);
        });

        // grc_risk_reviews — periodic risk review log
        Schema::create('grc_risk_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('risk_id')->constrained('grc_risks')->cascadeOnDelete();
            $table->date('review_date');
            $table->unsignedTinyInteger('reviewed_likelihood');
            $table->unsignedTinyInteger('reviewed_impact');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['risk_id', 'review_date']);
        });

        // grc_kris — KRI definitions
        Schema::create('grc_kris', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('risk_id')->nullable()->constrained('grc_risks')->nullOnDelete();
            $table->string('kri_code', 30);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('data_source', 100);      // e.g., 'invoices', 'journal_entries', 'manual'
            $table->string('metric_field', 100)->nullable(); // field to measure
            $table->enum('aggregation', ['count', 'sum', 'avg', 'max', 'min', 'percentage'])->default('count');
            $table->decimal('threshold_green', 15, 4);  // Below this = green
            $table->decimal('threshold_amber', 15, 4);  // Below this = amber
            $table->decimal('threshold_red', 15, 4);    // At or above = red
            $table->enum('direction', ['lower_is_better', 'higher_is_better'])->default('lower_is_better');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly'])->default('monthly');
            $table->timestamp('last_measured_at')->nullable();
            $table->decimal('last_value', 15, 4)->nullable();
            $table->enum('last_status', ['green', 'amber', 'red'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'kri_code']);
            $table->index(['organization_id', 'last_status']);
        });

        // grc_kri_readings — historical KRI values
        Schema::create('grc_kri_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kri_id')->constrained('grc_kris')->cascadeOnDelete();
            $table->date('reading_date');
            $table->decimal('value', 15, 4);
            $table->enum('status', ['green', 'amber', 'red']);
            $table->text('notes')->nullable();
            $table->boolean('is_auto')->default(true); // auto-computed vs manual
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['kri_id', 'reading_date']);
        });

        // grc_control_library — GRC-PC control definitions
        Schema::create('grc_control_library', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('control_code', 30);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('control_type', ['preventive', 'detective', 'corrective', 'directive']);
            $table->enum('control_category', ['it', 'manual', 'automated', 'semi_automated']);
            $table->string('module_reference', 50)->nullable();
            $table->enum('frequency', ['continuous', 'daily', 'weekly', 'monthly', 'quarterly', 'annual', 'ad_hoc'])->default('monthly');
            $table->enum('status', ['active', 'inactive', 'under_review'])->default('active');
            $table->foreignId('control_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'control_code']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grc_control_library');
        Schema::dropIfExists('grc_kri_readings');
        Schema::dropIfExists('grc_kris');
        Schema::dropIfExists('grc_risk_reviews');
        Schema::dropIfExists('grc_risk_treatments');
        Schema::dropIfExists('grc_risks');
        Schema::dropIfExists('grc_risk_categories');
    }
};
