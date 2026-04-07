<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add requires_inspection flag to products
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('requires_inspection')->default(false)->after('track_inventory');
        });

        // Add inspection-related columns to goods_receipts
        Schema::table('goods_receipts', function (Blueprint $table): void {
            // FK to the inspection lot created for this GR (nullable — only set when QI triggered)
            $table->unsignedBigInteger('inspection_lot_id')->nullable()->after('journal_entry_id');
            $table->foreign('inspection_lot_id')
                ->references('id')
                ->on('inspection_lots')
                ->nullOnDelete();

            // Note: the status column in goods_receipts is a string (VARCHAR).
            // The 'in_inspection' value is added as a valid application-level status;
            // no enum alteration is required — existing rows and constraints remain valid.
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropForeign(['inspection_lot_id']);
            $table->dropColumn('inspection_lot_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('requires_inspection');
        });
    }
};
