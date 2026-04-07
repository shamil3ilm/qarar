<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('project_revenue_recognitions');
        Schema::dropIfExists('project_billing_milestones');
        Schema::dropIfExists('project_billing_rules');

        Schema::create('project_billing_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->enum('billing_type', ['milestone', 'time_material', 'fixed_price', 'percentage_completion']);
            $table->char('currency', 3);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('total_contract_value', 18, 4)->nullable();
            $table->decimal('retention_percentage', 5, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('customer_id', 'proj_bill_cust_fk')->references('id')->on('contacts')->nullOnDelete();
        });

        Schema::create('project_billing_milestones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('project_billing_rule_id');
            $table->string('milestone_name');
            $table->decimal('billing_amount', 18, 4);
            $table->decimal('billing_percentage', 5, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->enum('status', ['pending', 'invoiced', 'paid'])->default('pending');
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamps();

            $table->foreign('project_billing_rule_id', 'proj_bill_ms_rule_fk')->references('id')->on('project_billing_rules')->cascadeOnDelete();
            $table->foreign('invoice_id', 'proj_bill_ms_inv_fk')->references('id')->on('invoices')->nullOnDelete();
        });

        Schema::create('project_revenue_recognitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedSmallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->decimal('recognized_revenue', 18, 4);
            $table->decimal('recognized_cost', 18, 4);
            $table->decimal('completion_percentage', 5, 2);
            $table->unsignedBigInteger('gl_account_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('gl_account_id', 'proj_rev_rec_gl_fk')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_revenue_recognitions');
        Schema::dropIfExists('project_billing_milestones');
        Schema::dropIfExists('project_billing_rules');
    }
};
