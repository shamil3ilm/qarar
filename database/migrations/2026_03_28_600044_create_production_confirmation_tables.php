<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('production_confirmations');

        Schema::create('production_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('confirmation_number')->unique();
            $table->foreignId('work_order_id')->constrained('work_orders', 'id', 'prod_conf_wo_fk');
            $table->foreignId('operation_id')
                ->nullable()
                ->constrained('work_order_operations', 'id', 'prod_conf_op_fk');
            $table->enum('confirmation_type', ['partial', 'final', 'milestone']);
            $table->decimal('confirmed_quantity', 18, 4);
            $table->decimal('scrap_quantity', 18, 4)->default(0);
            $table->decimal('rework_quantity', 18, 4)->default(0);
            $table->decimal('actual_setup_time', 8, 2)->nullable();
            $table->decimal('actual_machine_time', 8, 2)->nullable();
            $table->decimal('actual_labor_time', 8, 2)->nullable();
            $table->enum('time_uom', ['minutes', 'hours'])->default('minutes');
            $table->foreignId('confirmed_by')->constrained('users', 'id', 'prod_conf_usr_fk');
            $table->timestamp('confirmed_at');
            $table->date('posting_date');
            $table->enum('shift', ['morning', 'afternoon', 'night'])->nullable();
            $table->boolean('is_final')->default(false);
            $table->foreignId('reversal_id')
                ->nullable()
                ->constrained('production_confirmations', 'id', 'prod_conf_rev_fk');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_confirmations');
    }
};
