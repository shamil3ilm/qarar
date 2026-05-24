<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_decisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_lot_id')->constrained('inspection_lots')->cascadeOnDelete();
            $table->string('decision_number')->unique();

            // Overall decision outcome for the lot
            $table->enum('decision_code', ['accept', 'reject', 'partial'])->default('accept');

            // Quantitative split across stock types
            $table->decimal('qty_unrestricted', 12, 4)->default(0); // → movement 321
            $table->decimal('qty_blocked', 12, 4)->default(0);       // → movement 346
            $table->decimal('qty_scrap', 12, 4)->default(0);         // → movement 551

            $table->text('notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'inspection_lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_decisions');
    }
};
