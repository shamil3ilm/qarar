<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_expense_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->enum('category', ['accommodation', 'transport', 'meals', 'entertainment', 'other']);
            $table->decimal('daily_limit', 15, 4)->nullable();
            $table->string('gl_account_code', 20)->nullable();
            $table->boolean('requires_receipt')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('travel_expense_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('report_number', 30)->unique();
            $table->foreignId('travel_request_id')->nullable()->constrained('travel_requests')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('report_date');
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'posted'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('journal_entry_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'employee_id']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('travel_expense_report_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_report_id')->constrained('travel_expense_reports')->cascadeOnDelete();
            $table->foreignId('expense_type_id')->constrained('travel_expense_types')->restrictOnDelete();
            $table->date('expense_date');
            $table->text('description');
            $table->decimal('amount', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('amount_in_local', 15, 4)->nullable();
            $table->boolean('receipt_attached')->default(false);
            $table->string('receipt_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_expense_report_lines');
        Schema::dropIfExists('travel_expense_reports');
        Schema::dropIfExists('travel_expense_types');
    }
};
