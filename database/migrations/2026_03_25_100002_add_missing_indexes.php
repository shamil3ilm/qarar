<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // invoice_lines.product_id — not indexed in original migration
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->index('product_id');
        });

        // payslip_items.payslip_id — already indexed in original migration (2024_01_07_000005),
        // so we skip it here to avoid a duplicate index error.
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });
    }
};
