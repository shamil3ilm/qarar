<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('manager_team_views');
        Schema::dropIfExists('manager_delegations');

        Schema::create('manager_delegations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete()->name('md_manager_fk');
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete()->name('md_delegate_fk');
            $table->enum('delegation_type', ['full', 'leave_approval', 'attendance_approval', 'expense_approval'])->default('full');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['manager_id', 'is_active'], 'md_manager_active_idx');
            $table->index(['delegate_id', 'is_active'], 'md_delegate_active_idx');
        });

        Schema::create('manager_team_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->name('mtv_org_fk');
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete()->name('mtv_manager_fk');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->name('mtv_employee_fk');
            $table->enum('relationship_type', ['direct_report', 'indirect_report'])->default('direct_report');
            $table->timestamps();

            $table->unique(['manager_id', 'employee_id'], 'mtv_manager_employee_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_team_views');
        Schema::dropIfExists('manager_delegations');
    }
};
