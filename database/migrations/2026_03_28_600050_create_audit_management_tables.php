<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('audit_findings');
        Schema::dropIfExists('audit_checklists');
        Schema::dropIfExists('audit_reports');
        Schema::dropIfExists('audit_plans');

        Schema::create('audit_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->string('plan_number', 50)->unique();
            $table->string('title');
            $table->enum('audit_type', ['internal', 'supplier', 'customer', 'regulatory', 'certification'])->default('internal');
            $table->date('planned_start');
            $table->date('planned_end');
            $table->unsignedBigInteger('lead_auditor_id')->nullable();
            $table->foreign('lead_auditor_id', 'audit_plan_auditor_fk')->references('id')->on('users')->nullOnDelete();
            $table->enum('status', ['draft', 'approved', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->text('scope')->nullable();
            $table->text('objectives')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audit_checklists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('audit_plan_id');
            $table->foreign('audit_plan_id', 'audit_cl_plan_fk')->references('id')->on('audit_plans')->cascadeOnDelete();
            $table->string('item_number', 20);
            $table->text('question');
            $table->enum('response', ['yes', 'no', 'partial', 'na'])->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('audit_plan_id');
            $table->foreign('audit_plan_id', 'audit_rpt_plan_fk')->references('id')->on('audit_plans')->cascadeOnDelete();
            $table->date('report_date');
            $table->text('executive_summary')->nullable();
            $table->text('conclusions')->nullable();
            $table->enum('overall_rating', ['satisfactory', 'needs_improvement', 'unsatisfactory'])->nullable();
            $table->timestamps();
        });

        Schema::create('audit_findings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('audit_plan_id');
            $table->foreign('audit_plan_id', 'audit_finding_plan_fk')->references('id')->on('audit_plans')->cascadeOnDelete();
            $table->string('finding_number', 30);
            $table->enum('finding_type', ['major_nc', 'minor_nc', 'observation', 'positive'])->default('minor_nc');
            $table->text('description');
            $table->text('requirement_reference')->nullable();
            $table->text('evidence')->nullable();
            $table->enum('status', ['open', 'in_progress', 'closed', 'verified'])->default('open');
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_findings');
        Schema::dropIfExists('audit_checklists');
        Schema::dropIfExists('audit_reports');
        Schema::dropIfExists('audit_plans');
    }
};
