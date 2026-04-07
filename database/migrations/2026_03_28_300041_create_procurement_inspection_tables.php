<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_inspection_configs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->boolean('inspection_required')->default(false);
            $table->decimal('sampling_percentage', 5, 2)->default(100);
            $table->decimal('auto_approve_below_defect_rate', 5, 2)->nullable();
            $table->foreignId('quality_plan_id')->nullable()->constrained('quality_plans')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'product_id', 'vendor_id'], 'proc_insp_cfg_org_prod_vnd');
        });

        Schema::create('procurement_inspections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->unsignedBigInteger('goods_receipt_id')->nullable();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('vendor_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('inspection_lot_id')->nullable()->constrained('inspection_lots')->nullOnDelete();
            $table->decimal('quantity_received', 18, 4);
            $table->decimal('quantity_to_inspect', 18, 4);
            $table->decimal('quantity_inspected', 18, 4)->default(0);
            $table->decimal('quantity_accepted', 18, 4)->default(0);
            $table->decimal('quantity_rejected', 18, 4)->default(0);
            $table->string('status', 20)->default('pending')
                ->comment('pending/in_progress/completed/approved/rejected');
            $table->decimal('defect_rate', 5, 2)->nullable();
            $table->dateTime('inspection_date')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['purchase_order_id', 'status'], 'proc_insp_po_status_idx');
            $table->index(['product_id', 'vendor_id'], 'proc_insp_prod_vnd_idx');
            $table->index(['status', 'inspection_date'], 'proc_insp_status_date_idx');
        });

        Schema::create('procurement_inspection_results', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('procurement_inspection_id')
                ->constrained('procurement_inspections')
                ->cascadeOnDelete();
            $table->string('characteristic_name', 100);
            $table->string('specification_min', 50)->nullable();
            $table->string('specification_max', 50)->nullable();
            $table->string('actual_value', 100)->nullable();
            $table->boolean('is_within_spec')->nullable();
            $table->text('defect_description')->nullable();
            $table->timestamps();

            $table->index('procurement_inspection_id', 'proc_insp_res_insp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_inspection_results');
        Schema::dropIfExists('procurement_inspections');
        Schema::dropIfExists('procurement_inspection_configs');
    }
};
