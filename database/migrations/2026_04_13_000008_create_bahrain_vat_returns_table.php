<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bahrain VAT returns (National Bureau for Revenue — NBR).
 *
 * Bahrain introduced 5% VAT in 2019 and increased it to 10% on 1 January 2022.
 * VAT returns are filed quarterly (or monthly for large taxpayers).
 *
 * Return fields follow the official NBR VAT Return form:
 *  Box 1:  Standard-rated supplies (10%)
 *  Box 2:  Zero-rated supplies
 *  Box 3:  Exempt supplies
 *  Box 4:  Output VAT due
 *  Box 5:  Standard-rated purchases (input tax reclaimable)
 *  Box 6:  Capital goods input tax
 *  Box 7:  Total input VAT
 *  Box 8:  Net VAT payable (Box 4 − Box 7) or refundable
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bahrain_vat_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Return period
            $table->enum('period_type', ['quarterly', 'monthly'])->default('quarterly');
            $table->tinyInteger('period_quarter')->nullable();  // 1-4 for quarterly
            $table->tinyInteger('period_month')->nullable();    // 1-12 for monthly
            $table->year('period_year');
            $table->date('period_start');
            $table->date('period_end');

            // Output VAT (sales)
            $table->decimal('standard_rated_supplies', 18, 4)->default(0);   // Box 1: taxable sales (BHD)
            $table->decimal('zero_rated_supplies', 18, 4)->default(0);       // Box 2
            $table->decimal('exempt_supplies', 18, 4)->default(0);           // Box 3
            $table->decimal('output_vat', 18, 4)->default(0);                // Box 4: 10% × Box 1

            // Input VAT (purchases)
            $table->decimal('standard_rated_purchases', 18, 4)->default(0); // Box 5
            $table->decimal('capital_goods_input_tax', 18, 4)->default(0);  // Box 6
            $table->decimal('total_input_vat', 18, 4)->default(0);          // Box 7

            // Net position
            $table->decimal('net_vat_payable', 18, 4)->default(0);          // Box 8 (negative = refund)

            // Tax parameters
            $table->decimal('vat_rate', 7, 4)->default(10.0);               // 10% (effective Jan 2022)

            // Workflow
            $table->enum('status', ['draft', 'submitted', 'accepted', 'paid'])->default('draft');
            $table->string('nbr_reference', 100)->nullable();
            $table->date('filing_due_date')->nullable();
            $table->date('filed_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'period_year', 'period_quarter', 'period_month'], 'bh_vat_period_unique');
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bahrain_vat_returns');
    }
};
