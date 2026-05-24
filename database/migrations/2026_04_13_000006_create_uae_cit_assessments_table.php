<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UAE Corporate Income Tax (CIT) assessments.
 *
 * UAE Federal Decree-Law No. 47 of 2022 on Corporate Tax:
 *  - 0% on taxable income ≤ AED 375,000
 *  - 9% on taxable income > AED 375,000
 *  - Small Business Relief: taxable persons with revenue ≤ AED 3,000,000 may elect 0% for
 *    tax periods ending on or before 31 December 2026.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uae_cit_assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();

            $table->year('tax_year');

            // Income computation
            $table->decimal('accounting_income', 18, 4)->default(0);    // net profit per books
            $table->decimal('add_backs', 18, 4)->default(0);            // non-deductible expenses
            $table->decimal('deductions', 18, 4)->default(0);           // exempt income / allowances
            $table->decimal('taxable_income', 18, 4)->default(0);       // accounting_income + add_backs - deductions

            // Thresholds (AED)
            $table->decimal('zero_rate_threshold', 18, 4)->default(375000.0);   // AED 375,000
            $table->decimal('small_business_threshold', 18, 4)->default(3000000.0); // AED 3,000,000

            // Tax computation
            $table->decimal('cit_rate', 7, 4)->default(9.0000);         // 9%
            $table->boolean('small_business_relief')->default(false);   // elected SBR
            $table->decimal('cit_due', 18, 4)->default(0);              // payable tax

            // Payments
            $table->decimal('cit_paid', 18, 4)->default(0);
            $table->decimal('cit_remaining', 18, 4)->default(0);

            // Workflow
            $table->enum('status', ['draft', 'submitted', 'assessed', 'paid'])->default('draft');
            $table->string('emara_tax_reference', 100)->nullable();
            $table->date('filing_due_date')->nullable();
            $table->date('filed_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'tax_year'], 'cit_org_year_unique');
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uae_cit_assessments');
    }
};
