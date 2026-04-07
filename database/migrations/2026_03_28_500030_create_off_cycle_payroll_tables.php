<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('off_cycle_payroll_items');
        Schema::dropIfExists('off_cycle_payroll_runs');

        Schema::create('off_cycle_payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->enum('run_type', ['bonus', 'termination', 'correction', 'advance_recovery', 'other'])->default('bonus');
            $table->string('run_name', 100);
            $table->date('run_date');
            $table->enum('status', ['draft', 'processing', 'completed', 'cancelled'])->default('draft');
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_gross', 18, 4)->default(0);
            $table->decimal('total_net', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete()->name('ocpr_processed_by_fk');
            $table->datetime('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'run_date'], 'ocpr_org_date_idx');
        });

        Schema::create('off_cycle_payroll_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->name('ocpi_org_fk');
            $table->foreignId('off_cycle_payroll_run_id')->constrained('off_cycle_payroll_runs')->cascadeOnDelete()->name('ocpi_run_fk');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->name('ocpi_employee_fk');
            $table->string('component_code', 50);
            $table->string('component_name', 100);
            $table->decimal('amount', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('net_amount', 18, 4);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['off_cycle_payroll_run_id'], 'ocpi_run_idx');
            $table->index(['employee_id'], 'ocpi_employee_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('off_cycle_payroll_items');
        Schema::dropIfExists('off_cycle_payroll_runs');
    }
};
