<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------------
        // Cost Centers
        // ----------------------------------------------------------------
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedBigInteger('gl_account_id')->nullable();
            $table->boolean('is_statistical')->default(false);
            $table->softDeletes();
            $table->timestamps();

            // Unique code per organization
            $table->unique(['organization_id', 'code']);

            // Foreign keys
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->onDelete('cascade');

            $table->foreign('parent_id')
                ->references('id')->on('cost_centers')
                ->onDelete('set null');

            $table->foreign('manager_id')
                ->references('id')->on('employees')
                ->onDelete('set null');

            $table->foreign('department_id')
                ->references('id')->on('departments')
                ->onDelete('set null');

            $table->foreign('gl_account_id')
                ->references('id')->on('chart_of_accounts')
                ->onDelete('set null');

            // Indexes
            $table->index(['organization_id', 'status']);
            $table->index('organization_id');
        });

        // ----------------------------------------------------------------
        // Profit Centers
        // ----------------------------------------------------------------
        Schema::create('profit_centers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedBigInteger('gl_account_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);

            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->onDelete('cascade');

            $table->foreign('parent_id')
                ->references('id')->on('profit_centers')
                ->onDelete('set null');

            $table->foreign('manager_id')
                ->references('id')->on('employees')
                ->onDelete('set null');

            $table->foreign('gl_account_id')
                ->references('id')->on('chart_of_accounts')
                ->onDelete('set null');

            $table->index(['organization_id', 'status']);
            $table->index('organization_id');
        });

        // ----------------------------------------------------------------
        // Cost Center Assignments  (polymorphic: employees, departments …)
        // ----------------------------------------------------------------
        Schema::create('cost_center_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('assignable_type', 191);
            $table->unsignedBigInteger('assignable_id');
            $table->unsignedBigInteger('cost_center_id');
            $table->unsignedBigInteger('profit_center_id')->nullable();
            $table->decimal('split_percent', 5, 2)->default(100.00);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->onDelete('cascade');

            $table->foreign('cost_center_id')
                ->references('id')->on('cost_centers')
                ->onDelete('cascade');

            $table->foreign('profit_center_id')
                ->references('id')->on('profit_centers')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')->on('users')
                ->onDelete('set null');

            $table->index(['assignable_type', 'assignable_id']);
            $table->index('cost_center_id');
            $table->index('organization_id');
        });

        // ----------------------------------------------------------------
        // Cost Allocations
        // ----------------------------------------------------------------
        Schema::create('cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('fiscal_year_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('from_cost_center_id');
            $table->unsignedBigInteger('to_cost_center_id');
            $table->enum('allocation_method', ['fixed', 'percentage', 'activity'])->default('percentage');
            $table->decimal('allocation_percent', 5, 2)->nullable();
            $table->decimal('allocation_amount', 15, 4)->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->onDelete('cascade');

            $table->foreign('fiscal_year_id')
                ->references('id')->on('fiscal_years')
                ->onDelete('set null');

            $table->foreign('from_cost_center_id')
                ->references('id')->on('cost_centers')
                ->onDelete('restrict');

            $table->foreign('to_cost_center_id')
                ->references('id')->on('cost_centers')
                ->onDelete('restrict');

            $table->foreign('journal_entry_id')
                ->references('id')->on('journal_entries')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')->on('users')
                ->onDelete('set null');

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_allocations');
        Schema::dropIfExists('cost_center_assignments');
        Schema::dropIfExists('profit_centers');
        Schema::dropIfExists('cost_centers');
    }
};
