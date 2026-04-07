<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('quotation_id')
                ->references('id')
                ->on('quotations')
                ->nullOnDelete();

            $table->foreign('sales_order_id')
                ->references('id')
                ->on('sales_orders')
                ->nullOnDelete();

            $table->index('quotation_id');
            $table->index('sales_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->dropForeign(['sales_order_id']);
            $table->dropIndex(['quotation_id']);
            $table->dropIndex(['sales_order_id']);
        });
    }
};
