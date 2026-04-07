<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // saved_reports and report_executions are created in
        // 2024_01_25_000005_create_periodic_reports_tables.php

        // Financial snapshots for period-end balances
        Schema::create('financial_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->string('period_type', 20); // daily, monthly, quarterly, yearly
            $table->decimal('opening_balance', 20, 4)->default(0);
            $table->decimal('debit_total', 20, 4)->default(0);
            $table->decimal('credit_total', 20, 4)->default(0);
            $table->decimal('closing_balance', 20, 4)->default(0);
            $table->decimal('base_opening_balance', 20, 4)->default(0);
            $table->decimal('base_closing_balance', 20, 4)->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'fiscal_year_id', 'account_id', 'snapshot_date', 'period_type'], 'financial_snapshots_unique');
            $table->index(['organization_id', 'snapshot_date']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('financial_snapshots');
    }
};
