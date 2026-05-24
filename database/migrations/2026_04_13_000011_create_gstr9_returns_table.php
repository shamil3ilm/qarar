<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSTR-9 Annual Return table.
 *
 * GSTR-9 is the annual GST return filed by regular taxpayers. It consolidates
 * data from all monthly GSTR-1, GSTR-2A, and GSTR-3B returns for the FY.
 *
 * Key tables in the GSTR-9 form:
 *   Table 4  — Outward supplies (taxable, zero-rated, exempt)
 *   Table 5  — Outward supplies (nil-rated, non-GST)
 *   Table 6  — ITC availed (inputs, input services, capital goods)
 *   Table 7  — ITC reversed
 *   Table 8  — ITC comparison with GSTR-2A
 *   Table 9  — Tax paid as declared in GSTR-3B
 *   Table 10 — Supplies/tax declared through amendments (next FY)
 *   Table 11 — ITC availed through amendments
 *   Table 14 — HSN summary (outward supplies)
 *   Table 15 — Demands and refunds
 *   Table 16 — Info about supplies received from composition / deemed supply
 *   Table 17 — HSN summary (inward supplies)
 *   Table 18 — Late fees
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gstr9_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('gstin', 15);             // 15-char GSTIN
            $table->year('financial_year_start');    // e.g. 2024 for FY 2024-25

            // Table 4: Taxable outward supplies
            $table->decimal('t4a_taxable_supplies', 18, 2)->default(0);   // 4A: taxable B2B
            $table->decimal('t4b_zero_rated', 18, 2)->default(0);         // 4B: zero-rated supplies
            $table->decimal('t4c_nil_rated', 18, 2)->default(0);          // 4C: nil-rated / exempt B2B

            // Output tax (Table 9)
            $table->decimal('t9_igst_payable', 18, 2)->default(0);
            $table->decimal('t9_cgst_payable', 18, 2)->default(0);
            $table->decimal('t9_sgst_payable', 18, 2)->default(0);
            $table->decimal('t9_cess_payable', 18, 2)->default(0);
            $table->decimal('t9_igst_paid', 18, 2)->default(0);
            $table->decimal('t9_cgst_paid', 18, 2)->default(0);
            $table->decimal('t9_sgst_paid', 18, 2)->default(0);
            $table->decimal('t9_cess_paid', 18, 2)->default(0);

            // ITC (Table 6)
            $table->decimal('t6a_itc_inputs', 18, 2)->default(0);        // inputs
            $table->decimal('t6b_itc_input_services', 18, 2)->default(0); // input services
            $table->decimal('t6c_itc_capital_goods', 18, 2)->default(0); // capital goods
            $table->decimal('t6_total_itc', 18, 2)->default(0);          // total availed

            // ITC reversed (Table 7)
            $table->decimal('t7_itc_reversed', 18, 2)->default(0);

            // Net ITC available
            $table->decimal('net_itc', 18, 2)->default(0);               // t6_total_itc - t7_itc_reversed

            // Late fees (Table 18)
            $table->decimal('t18_late_fee_cgst', 18, 2)->default(0);
            $table->decimal('t18_late_fee_sgst', 18, 2)->default(0);

            // Workflow
            $table->enum('status', ['draft', 'filed', 'accepted'])->default('draft');
            $table->string('gstn_arn', 30)->nullable();   // ARN after filing
            $table->date('filed_date')->nullable();
            $table->date('due_date')->nullable();         // 31 Dec following FY end

            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'gstin', 'financial_year_start'], 'gstr9_org_gstin_fy_unique');
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gstr9_returns');
    }
};
