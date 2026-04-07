<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('position_assignments');
        Schema::dropIfExists('org_positions');
        Schema::dropIfExists('org_units');

        Schema::create('org_units', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->string('short_name', 20)->nullable();
            $table->enum('unit_type', ['company', 'division', 'department', 'team', 'cost_center_group']);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id', 'org_unit_parent_fk')->references('id')->on('org_units')->onDelete('set null');
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->foreign('manager_id', 'org_unit_mgr_fk')->references('id')->on('employees')->onDelete('set null');
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->foreign('cost_center_id', 'org_unit_cc_fk')->references('id')->on('cost_centers')->onDelete('set null');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('org_positions', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('position_title');
            $table->unsignedBigInteger('org_unit_id');
            $table->foreign('org_unit_id', 'org_pos_unit_fk')->references('id')->on('org_units')->onDelete('cascade');
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->foreign('designation_id', 'org_pos_desig_fk')->references('id')->on('designations')->onDelete('set null');
            $table->unsignedBigInteger('reports_to_position_id')->nullable();
            $table->foreign('reports_to_position_id', 'org_pos_rpt_fk')->references('id')->on('org_positions')->onDelete('set null');
            $table->unsignedSmallInteger('headcount_budget')->default(1);
            $table->unsignedSmallInteger('current_headcount')->default(0);
            $table->boolean('is_key_position')->default(false);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('position_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id', 'pos_assign_emp_fk')->references('id')->on('employees')->onDelete('cascade');
            $table->unsignedBigInteger('org_position_id');
            $table->foreign('org_position_id', 'pos_assign_pos_fk')->references('id')->on('org_positions')->onDelete('cascade');
            $table->enum('assignment_type', ['primary', 'secondary', 'acting'])->default('primary');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_assignments');
        Schema::dropIfExists('org_positions');
        Schema::dropIfExists('org_units');
    }
};
