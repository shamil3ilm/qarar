<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('billing_plan_items');
        Schema::dropIfExists('billing_plans');

        Schema::create('billing_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete()->name('bp_so_fk');
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete()->name('bp_quot_fk');
            $table->enum('plan_type', ['milestone', 'periodic'])->default('milestone');
            $table->char('billing_currency', 3)->default('SAR');
            $table->decimal('total_value', 18, 4)->default(0);
            $table->decimal('billed_value', 18, 4)->default(0);
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('periodic_interval_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'sales_order_id'], 'bp_org_so_idx');
        });

        Schema::create('billing_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->name('bpi_org_fk');
            $table->foreignId('billing_plan_id')->constrained('billing_plans')->cascadeOnDelete()->name('bpi_plan_fk');
            $table->string('milestone_description', 255)->nullable();
            $table->date('billing_date');
            $table->decimal('billing_percent', 5, 2)->nullable();
            $table->decimal('billing_amount', 18, 4);
            $table->enum('status', ['pending', 'billed', 'cancelled'])->default('pending');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete()->name('bpi_invoice_fk');
            $table->dateTime('billed_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['billing_plan_id', 'billing_date'], 'bpi_plan_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plan_items');
        Schema::dropIfExists('billing_plans');
    }
};
