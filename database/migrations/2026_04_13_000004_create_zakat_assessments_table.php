<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zakat Assessment — annual Zakat liability for Saudi organizations (ZATCA / GAZT).
 *
 * Zakat is levied on Saudi/GCC national shareholders at 2.5% of the Zakat base
 * (adjusted net assets minus non-Zakat deductions), calculated on a Hijri year basis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zakat_assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();

            // Assessment period (Gregorian dates, Hijri reference stored as string)
            $table->year('assessment_year');
            $table->string('hijri_year', 10)->nullable(); // e.g. "1447"

            // Zakat base components (SAR)
            $table->decimal('total_assets', 18, 4)->default(0);
            $table->decimal('total_liabilities', 18, 4)->default(0);
            $table->decimal('non_zakatable_assets', 18, 4)->default(0);  // fixed assets, investments
            $table->decimal('zakat_base', 18, 4)->default(0);             // total_assets - total_liabilities - non_zakatable_assets
            $table->decimal('zakat_rate', 7, 4)->default(2.5000);         // 2.5%
            $table->decimal('zakat_due', 18, 4)->default(0);              // zakat_base × rate / 100

            // Saudi-national shareholder proportion (for mixed ownership companies)
            $table->decimal('saudi_ownership_pct', 7, 4)->default(100.0000); // 100% for fully Saudi-owned

            // Adjustments
            $table->decimal('zakat_paid', 18, 4)->default(0);
            $table->decimal('zakat_remaining', 18, 4)->default(0);

            // Status workflow
            $table->enum('status', ['draft', 'submitted', 'assessed', 'paid'])->default('draft');
            $table->string('gazt_reference', 100)->nullable();   // GAZT / ZATCA filing reference
            $table->date('filing_due_date')->nullable();
            $table->date('filed_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'assessment_year'], 'zakat_org_year_unique');
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zakat_assessments');
    }
};
