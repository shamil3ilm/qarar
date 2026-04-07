<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('commission_payments');
        Schema::dropIfExists('commission_calculations');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('commission_masters');

        Schema::create('commission_masters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('sales_rep_id');
            $table->foreign('sales_rep_id', 'comm_master_usr_fk')->references('id')->on('users')->onDelete('cascade');
            $table->string('commission_plan_name');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('base_rate', 8, 4);
            $table->char('currency', 3)->default('SAR');
            $table->decimal('quota_amount', 18, 4)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('commission_master_id');
            $table->foreign('commission_master_id', 'comm_rule_master_fk')->references('id')->on('commission_masters')->onDelete('cascade');
            $table->enum('rule_type', ['flat', 'tiered', 'product_category', 'customer_group']);
            $table->string('condition_field')->nullable();
            $table->string('condition_value')->nullable();
            $table->decimal('rate', 8, 4);
            $table->decimal('tier_from', 18, 4)->nullable();
            $table->decimal('tier_to', 18, 4)->nullable();
            $table->unsignedSmallInteger('priority')->default(10);
            $table->timestamps();
        });

        Schema::create('commission_calculations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('commission_master_id');
            $table->foreign('commission_master_id', 'comm_calc_master_fk')->references('id')->on('commission_masters')->onDelete('cascade');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->foreign('invoice_id', 'comm_calc_inv_fk')->references('id')->on('invoices')->onDelete('set null');
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->foreign('sales_order_id', 'comm_calc_so_fk')->references('id')->on('sales_orders')->onDelete('set null');
            $table->unsignedSmallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->decimal('base_amount', 18, 4);
            $table->decimal('commission_rate', 8, 4);
            $table->decimal('commission_amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->enum('status', ['calculated', 'approved', 'paid', 'reversed'])->default('calculated');
            $table->timestamp('calculated_at');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by', 'comm_calc_appr_fk')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('commission_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('payment_reference')->unique();
            $table->unsignedBigInteger('sales_rep_id');
            $table->foreign('sales_rep_id', 'comm_pay_usr_fk')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedSmallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->decimal('total_amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->date('payment_date');
            $table->enum('status', ['pending', 'processed'])->default('pending');
            $table->unsignedBigInteger('payslip_id')->nullable();
            $table->foreign('payslip_id', 'comm_pay_payslip_fk')->references('id')->on('payslips')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payments');
        Schema::dropIfExists('commission_calculations');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('commission_masters');
    }
};
