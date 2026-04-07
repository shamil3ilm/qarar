<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benefit_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->enum('category', ['allowance', 'insurance', 'other'])->default('allowance');
            $table->enum('calculation_type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('default_amount', 15, 4)->default(0);
            $table->decimal('percentage_basis', 5, 2)->nullable();
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('eligibility_rules')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'category', 'is_active'], 'ben_typ_org_cat_active_idx');
        });

        Schema::create('employee_benefits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('benefit_type_id')->constrained('benefit_types')->cascadeOnDelete();
            $table->decimal('amount', 15, 4)->default(0);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->string('policy_number', 100)->nullable();
            $table->string('provider_name', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status'], 'emp_ben_emp_status_idx');
            $table->index(['organization_id', 'benefit_type_id'], 'emp_ben_org_type_idx');
        });

        Schema::create('benefit_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_benefit_id')->constrained('employee_benefits')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('change_type', 30);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['employee_benefit_id'], 'ben_chg_benefit_idx');
            $table->index(['employee_id', 'changed_at'], 'ben_chg_emp_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benefit_changes');
        Schema::dropIfExists('employee_benefits');
        Schema::dropIfExists('benefit_types');
    }
};
