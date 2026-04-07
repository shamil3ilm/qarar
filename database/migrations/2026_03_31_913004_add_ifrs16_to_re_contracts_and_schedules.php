<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IFRS 16 — Right-of-Use assets and lease liability amortisation.
 *
 * Adds IFRS 16 measurement fields to re_contracts (lessee side) and
 * creates re_ifrs16_schedules for the period-by-period amortisation table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- IFRS 16 columns on lease contracts ----
        Schema::table('re_contracts', function (Blueprint $table): void {
            // Only lessee contracts (contract_type = 'lease_in') use IFRS 16.
            $table->decimal('ibr_percent', 8, 4)->nullable()->after('auto_renew_months')
                ->comment('Incremental Borrowing Rate used for PV calculation (annual %)');
            $table->decimal('rou_asset_amount', 20, 4)->nullable()->after('ibr_percent')
                ->comment('Right-of-Use asset value at commencement (= initial lease liability)');
            $table->decimal('lease_liability_amount', 20, 4)->nullable()->after('rou_asset_amount')
                ->comment('Remaining lease liability (updated each period)');
            $table->date('ifrs16_commencement_date')->nullable()->after('lease_liability_amount')
                ->comment('Date on which IFRS 16 recognition started');
            $table->boolean('ifrs16_applied')->default(false)->after('ifrs16_commencement_date');
        });

        // ---- IFRS 16 amortisation schedule ----
        Schema::create('re_ifrs16_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->date('period_date')->comment('First day of the accounting period');
            $table->decimal('opening_liability', 20, 4);
            $table->decimal('interest_expense', 20, 4)->comment('Opening liability × monthly IBR');
            $table->decimal('lease_payment', 20, 4)->comment('Contractual rent payment');
            $table->decimal('principal_reduction', 20, 4)->comment('Payment minus interest');
            $table->decimal('closing_liability', 20, 4);
            $table->decimal('rou_depreciation', 20, 4)->comment('Straight-line depreciation of ROU asset');
            $table->decimal('rou_book_value', 20, 4)->comment('ROU asset net book value at end of period');
            $table->boolean('gl_posted')->default(false);
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('re_contracts')->cascadeOnDelete();
            $table->unique(['contract_id', 'period_date']);
            $table->index(['contract_id', 'gl_posted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('re_ifrs16_schedules');

        Schema::table('re_contracts', function (Blueprint $table): void {
            $table->dropColumn([
                'ibr_percent',
                'rou_asset_amount',
                'lease_liability_amount',
                'ifrs16_commencement_date',
                'ifrs16_applied',
            ]);
        });
    }
};
