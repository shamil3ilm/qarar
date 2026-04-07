<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('probation_periods');

        Schema::create('probation_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->name('pp_employee_fk');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('extended_end_date')->nullable();
            $table->enum('status', ['active', 'completed', 'extended', 'failed', 'waived'])->default('active');
            $table->date('review_date')->nullable();
            $table->enum('outcome', ['confirmed', 'extended', 'terminated'])->nullable();
            $table->date('outcome_date')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete()->name('pp_reviewer_fk');
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'employee_id'], 'probation_org_employee_idx');
            $table->index(['organization_id', 'status'], 'probation_org_status_idx');
            $table->index(['end_date'], 'probation_end_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('probation_periods');
    }
};
