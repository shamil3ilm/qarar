<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shop_floor_confirmations');
        Schema::dropIfExists('shop_floor_papers');

        Schema::create('shop_floor_papers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('paper_number')->unique();
            $table->foreignId('work_order_id')->constrained('work_orders', 'id', 'sfp_wo_fk');
            $table->enum('paper_type', [
                'operation_sheet',
                'component_list',
                'routing_sheet',
                'traveler',
                'label',
            ]);
            $table->timestamp('printed_at')->nullable();
            $table->foreignId('printed_by')->nullable()->constrained('users', 'id', 'sfp_usr_fk');
            $table->unsignedSmallInteger('reprint_count')->default(0);
            $table->json('paper_data');
            $table->timestamps();
        });

        Schema::create('shop_floor_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('shop_floor_paper_id')->nullable()->constrained('shop_floor_papers', 'id', 'sfc_sfp_fk');
            $table->foreignId('work_order_id')->constrained('work_orders', 'id', 'sfc_wo_fk');
            $table->unsignedSmallInteger('operation_number')->nullable();
            $table->decimal('confirmed_quantity', 18, 4);
            $table->decimal('scrap_quantity', 18, 4)->default(0);
            $table->foreignId('confirmed_by')->constrained('users', 'id', 'sfc_usr_fk');
            $table->timestamp('confirmed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_floor_confirmations');
        Schema::dropIfExists('shop_floor_papers');
    }
};
