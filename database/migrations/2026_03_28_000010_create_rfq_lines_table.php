<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfq_headers')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->string('unit_of_measure', 20)->nullable();
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('rfq_id', 'rfq_lines_rfq_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_lines');
    }
};
