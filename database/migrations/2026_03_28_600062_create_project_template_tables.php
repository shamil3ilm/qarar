<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('project_template_milestones');
        Schema::dropIfExists('project_template_wbs');
        Schema::dropIfExists('project_templates');

        Schema::create('project_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('template_name');
            $table->text('description')->nullable();
            $table->enum('project_type', ['customer', 'internal', 'overhead', 'capital', 'maintenance']);
            $table->string('industry')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('created_by', 'proj_tmpl_usr_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('project_template_wbs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('project_template_id');
            $table->string('wbs_code');
            $table->string('description');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->decimal('planned_cost', 18, 4)->nullable();
            $table->unsignedBigInteger('responsible_dept_id')->nullable();
            $table->timestamps();

            $table->foreign('project_template_id', 'proj_tmpl_wbs_fk')->references('id')->on('project_templates')->cascadeOnDelete();
            $table->foreign('parent_id', 'proj_tmpl_wbs_par_fk')->references('id')->on('project_template_wbs')->nullOnDelete();
            $table->foreign('responsible_dept_id', 'proj_tmpl_wbs_dept_fk')->references('id')->on('departments')->nullOnDelete();
        });

        Schema::create('project_template_milestones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_template_id');
            $table->string('milestone_name');
            $table->unsignedSmallInteger('offset_days');
            $table->enum('milestone_type', ['start', 'gate', 'completion', 'billing']);
            $table->decimal('billing_percentage', 5, 2)->nullable();
            $table->timestamps();

            $table->foreign('project_template_id', 'proj_tmpl_ms_fk')->references('id')->on('project_templates')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_template_milestones');
        Schema::dropIfExists('project_template_wbs');
        Schema::dropIfExists('project_templates');
    }
};
