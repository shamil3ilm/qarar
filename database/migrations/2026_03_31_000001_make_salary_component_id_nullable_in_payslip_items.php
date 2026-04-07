<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payslip_items', function (Blueprint $table) {
            // Allow null for loan EMI and statutory deduction items that have
            // no corresponding salary component in the chart.
            $table->foreignId('salary_component_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payslip_items', function (Blueprint $table) {
            $table->foreignId('salary_component_id')->nullable(false)->change();
        });
    }
};
