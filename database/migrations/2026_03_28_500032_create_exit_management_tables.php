<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('exit_clearance_items');
        Schema::dropIfExists('employee_exits');

        Schema::create('employee_exits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->name('ee_employee_fk');
            $table->enum('exit_type', ['resignation', 'termination', 'retirement', 'contract_end', 'death'])->default('resignation');
            $table->date('resignation_date')->nullable();
            $table->date('last_working_date')->nullable();
            $table->unsignedSmallInteger('notice_period_days')->default(30);
            $table->boolean('notice_period_waived')->default(false);
            $table->text('exit_reason')->nullable();
            $table->enum('status', ['initiated', 'notice_period', 'clearance_in_progress', 'clearance_complete', 'settled', 'closed'])->default('initiated');
            $table->decimal('final_settlement_amount', 18, 4)->nullable();
            $table->date('settlement_date')->nullable();
            $table->decimal('eosb_amount', 18, 4)->nullable();
            $table->decimal('leave_encashment_amount', 18, 4)->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete()->name('ee_initiated_by_fk');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->name('ee_approved_by_fk');
            $table->datetime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'employee_id'], 'ee_org_employee_idx');
            $table->index(['organization_id', 'status'], 'ee_org_status_idx');
            $table->index(['last_working_date'], 'ee_last_working_date_idx');
        });

        Schema::create('exit_clearance_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->name('eci_org_fk');
            $table->foreignId('employee_exit_id')->constrained('employee_exits')->cascadeOnDelete()->name('eci_exit_fk');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete()->name('eci_dept_fk');
            $table->string('clearance_item', 100);
            $table->foreignId('responsible_person_id')->nullable()->constrained('users')->nullOnDelete()->name('eci_responsible_fk');
            $table->enum('status', ['pending', 'cleared', 'waived'])->default('pending');
            $table->datetime('cleared_at')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['employee_exit_id'], 'eci_exit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_clearance_items');
        Schema::dropIfExists('employee_exits');
    }
};
