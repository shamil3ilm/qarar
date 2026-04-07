<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_advance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_advance_id')->constrained('vendor_advances')->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->decimal('adjusted_amount', 15, 2);
            $table->timestamp('adjusted_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('vendor_advance_id', 'vendor_adv_adj_advance_id_idx');
            $table->index('bill_id', 'vendor_adv_adj_bill_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_advance_adjustments');
    }
};
