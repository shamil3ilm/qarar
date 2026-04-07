<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('financial_close_task_dependencies');
        Schema::dropIfExists('financial_close_tasks');
        Schema::dropIfExists('financial_close_periods');
        Schema::dropIfExists('financial_close_template_tasks');
        Schema::dropIfExists('financial_close_templates');

        Schema::create('financial_close_templates', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('close_type', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'fk_fct_org')
                ->references('id')->on('organizations')->onDelete('cascade');
        });

        Schema::create('financial_close_template_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('financial_close_template_id');
            $table->string('task_name');
            $table->text('description')->nullable();
            $table->string('task_type', 50);
            $table->integer('sort_order')->default(0);
            $table->decimal('estimated_duration_hours', 4, 1)->nullable();
            $table->string('required_role', 100)->nullable();
            $table->timestamps();

            $table->foreign('financial_close_template_id', 'fk_fctt_template')
                ->references('id')->on('financial_close_templates')->onDelete('cascade');
        });

        Schema::create('financial_close_periods', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('financial_close_template_id')->nullable();
            $table->smallInteger('fiscal_year');
            $table->tinyInteger('period');
            $table->string('close_type', 20);
            $table->string('status', 20)->default('open');
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'fk_fcp_org')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('financial_close_template_id', 'fk_fcp_template')
                ->references('id')->on('financial_close_templates')->onDelete('set null');
            $table->foreign('closed_by', 'fk_fcp_closed_by')
                ->references('id')->on('users')->onDelete('set null');

            $table->index(['organization_id', 'fiscal_year', 'period'], 'idx_fcp_org_period');
        });

        Schema::create('financial_close_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('financial_close_period_id');
            $table->unsignedBigInteger('template_task_id')->nullable();
            $table->string('task_name');
            $table->text('description')->nullable();
            $table->string('task_type', 50);
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('status', 20)->default('pending');
            $table->date('due_date')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('financial_close_period_id', 'fk_fctask_period')
                ->references('id')->on('financial_close_periods')->onDelete('cascade');
            $table->foreign('template_task_id', 'fk_fctask_tmpl_task')
                ->references('id')->on('financial_close_template_tasks')->onDelete('set null');
            $table->foreign('assigned_to', 'fk_fctask_assigned')
                ->references('id')->on('users')->onDelete('set null');
            $table->foreign('completed_by', 'fk_fctask_completed_by')
                ->references('id')->on('users')->onDelete('set null');

            $table->index(['financial_close_period_id', 'status'], 'idx_fctask_period_status');
            $table->index(['assigned_to', 'status'], 'idx_fctask_assignee_status');
        });

        Schema::create('financial_close_task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('financial_close_task_id');
            $table->unsignedBigInteger('depends_on_task_id');
            $table->timestamps();

            $table->foreign('financial_close_task_id', 'fk_fctdep_task')
                ->references('id')->on('financial_close_tasks')->onDelete('cascade');
            $table->foreign('depends_on_task_id', 'fk_fctdep_depends')
                ->references('id')->on('financial_close_tasks')->onDelete('cascade');

            $table->unique(
                ['financial_close_task_id', 'depends_on_task_id'],
                'uq_fctdep_task_dep'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_close_task_dependencies');
        Schema::dropIfExists('financial_close_tasks');
        Schema::dropIfExists('financial_close_periods');
        Schema::dropIfExists('financial_close_template_tasks');
        Schema::dropIfExists('financial_close_templates');
    }
};
