<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('competency_gap_analyses');
        Schema::dropIfExists('employee_skill_profiles');
        Schema::dropIfExists('competency_framework_skills');
        Schema::dropIfExists('competency_frameworks');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('skill_categories');

        Schema::create('skill_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id', 'skill_cat_parent_fk')->references('id')->on('skill_categories')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('skills', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('skill_category_id');
            $table->foreign('skill_category_id', 'skill_cat_fk')->references('id')->on('skill_categories')->onDelete('restrict');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('proficiency_scale')->default(5); // 1-5 or 1-10
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('competency_frameworks', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('applicable_to', ['all', 'department', 'designation', 'position'])->default('all');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('competency_framework_skills', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('competency_framework_id');
            $table->foreign('competency_framework_id', 'cf_skill_cf_fk')->references('id')->on('competency_frameworks')->onDelete('cascade');
            $table->unsignedBigInteger('skill_id');
            $table->foreign('skill_id', 'cf_skill_skill_fk')->references('id')->on('skills')->onDelete('cascade');
            $table->unsignedTinyInteger('required_level');
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->timestamps();
        });

        Schema::create('employee_skill_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id', 'emp_skill_emp_fk')->references('id')->on('employees')->onDelete('cascade');
            $table->unsignedBigInteger('skill_id');
            $table->foreign('skill_id', 'emp_skill_skill_fk')->references('id')->on('skills')->onDelete('cascade');
            $table->unsignedTinyInteger('current_level');
            $table->unsignedTinyInteger('target_level')->nullable();
            $table->unsignedBigInteger('assessed_by')->nullable();
            $table->foreign('assessed_by', 'emp_skill_assessor_fk')->references('id')->on('users')->onDelete('set null');
            $table->date('assessed_at')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('certification_ref')->nullable();
            $table->timestamps();
        });

        Schema::create('competency_gap_analyses', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id', 'comp_gap_emp_fk')->references('id')->on('employees')->onDelete('cascade');
            $table->unsignedBigInteger('framework_id');
            $table->foreign('framework_id', 'comp_gap_cf_fk')->references('id')->on('competency_frameworks')->onDelete('cascade');
            $table->date('analysis_date');
            $table->decimal('overall_score', 5, 2);
            $table->json('gaps'); // array of {skill_id, required, current, gap}
            $table->json('recommended_training')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_gap_analyses');
        Schema::dropIfExists('employee_skill_profiles');
        Schema::dropIfExists('competency_framework_skills');
        Schema::dropIfExists('competency_frameworks');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('skill_categories');
    }
};
