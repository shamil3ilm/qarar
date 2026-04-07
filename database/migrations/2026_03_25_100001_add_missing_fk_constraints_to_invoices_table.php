<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existingFks = collect(Schema::getForeignKeys('invoices'))->pluck('name')->toArray();

        Schema::table('invoices', function (Blueprint $table) use ($existingFks) {
            if (!in_array('invoices_quotation_id_foreign', $existingFks)) {
                $table->foreign('quotation_id')->references('id')->on('quotations')->nullOnDelete();
            }
            if (!in_array('invoices_sales_order_id_foreign', $existingFks)) {
                $table->foreign('sales_order_id')->references('id')->on('sales_orders')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->dropForeign(['sales_order_id']);
        });
    }
};
