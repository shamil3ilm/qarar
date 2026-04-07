<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gosi_contributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('total_salary', 15, 2)->default(0);
            $table->decimal('contributable_salary', 15, 2)->default(0);
            $table->decimal('employee_contribution', 15, 2)->default(0);
            $table->decimal('employer_contribution', 15, 2)->default(0);
            $table->decimal('hazard_contribution', 15, 2)->default(0);
            $table->decimal('total_contribution', 15, 2)->default(0);
            $table->string('gosi_id', 50)->nullable();
            $table->enum('status', ['draft', 'submitted', 'paid'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'employee_id', 'period_year', 'period_month'], 'gosi_contrib_emp_period_uniq');
            $table->index(['organization_id', 'period_year', 'period_month'], 'gosi_contrib_org_period_idx');
            $table->index(['organization_id', 'status'], 'gosi_contrib_org_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gosi_contributions');
    }
};
