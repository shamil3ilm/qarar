<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('transfer_number', 50);
            $table->date('effective_date');
            $table->enum('transfer_type', [
                'department',
                'position',
                'designation',
                'location',
                'manager',
                'lateral',
                'promotion',
                'demotion',
            ]);
            $table->string('reason', 500)->nullable();

            // From fields
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('from_designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('to_designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('from_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('to_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('from_reporting_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('to_reporting_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();

            // Workflow
            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'rejected',
                'applied',
            ])->default('draft');

            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'transfer_number']);
            $table->index(['organization_id', 'employee_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_transfers');
    }
};
