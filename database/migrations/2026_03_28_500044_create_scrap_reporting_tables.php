<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('scrap_reports');

        Schema::create('scrap_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete()->name('scr_wo_fk');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->name('scr_product_fk');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->name('scr_warehouse_fk');
            $table->date('scrap_date');
            $table->decimal('scrap_quantity', 18, 4);
            $table->string('unit_of_measure', 20)->nullable();
            $table->enum('scrap_cause', ['defect', 'damage', 'obsolete', 'process_loss', 'machine_failure', 'other'])->default('defect');
            $table->string('scrap_code', 30)->nullable();
            $table->text('description')->nullable();
            $table->decimal('estimated_value', 18, 4)->default(0);
            $table->boolean('is_recoverable')->default(false);
            $table->decimal('recovery_value', 18, 4)->default(0);
            $table->boolean('gl_posted')->default(false);
            $table->dateTime('gl_posted_at')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete()->name('scr_reported_by_fk');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'scrap_date'], 'scr_org_date_idx');
            $table->index(['work_order_id'], 'scr_wo_idx');
            $table->index(['product_id'], 'scr_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrap_reports');
    }
};
