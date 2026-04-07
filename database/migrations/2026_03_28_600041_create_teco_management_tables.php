<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('teco_reservation_clearances');
        Schema::dropIfExists('teco_records');

        Schema::create('teco_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('work_order_id')->unique()->constrained('work_orders', 'id', 'teco_wo_fk');
            $table->date('teco_date');
            $table->foreignId('teco_by')->constrained('users', 'id', 'teco_usr_fk');
            $table->decimal('remaining_quantity', 18, 4)->nullable();
            $table->enum('settlement_status', ['pending', 'settled', 'cancelled'])->default('pending');
            $table->date('settlement_date')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users', 'id', 'teco_rev_usr_fk');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('teco_reservation_clearances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teco_record_id')->constrained('teco_records', 'id', 'teco_clr_fk');
            $table->foreignId('material_id')->constrained('products', 'id', 'teco_clr_mat_fk');
            $table->decimal('cleared_quantity', 18, 4);
            $table->timestamp('cleared_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teco_reservation_clearances');
        Schema::dropIfExists('teco_records');
    }
};
