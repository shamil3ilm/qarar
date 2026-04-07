<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_contract_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_contract_id')->constrained('vendor_contracts')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->string('unit_of_measure', 20)->nullable();
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('vendor_contract_id', 'vendor_contract_items_contract_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_contract_items');
    }
};
