<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_vendor_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_vendor_id')->constrained('rfq_vendors')->cascadeOnDelete();
            $table->foreignId('rfq_line_id')->constrained('rfq_lines')->cascadeOnDelete();
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('total_price', 15, 4)->default(0);
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['rfq_vendor_id', 'rfq_line_id'], 'rfq_vq_vendor_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_vendor_quotes');
    }
};
