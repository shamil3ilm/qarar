<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mrp_planned_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('purchase_requisition_id')
                ->nullable()
                ->after('converted_to_id');

            $table->foreign('purchase_requisition_id')
                ->references('id')
                ->on('purchase_requisitions')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('mrp_planned_orders', function (Blueprint $table): void {
            $table->dropForeign(['purchase_requisition_id']);
            $table->dropColumn('purchase_requisition_id');
        });
    }
};
