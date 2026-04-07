<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rebate_masters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('contact_id');
            $table->string('rebate_type'); // percentage, fixed_amount, tiered
            $table->string('calculation_base'); // invoice_value, quantity, gross_profit
            $table->decimal('rebate_rate', 8, 4)->default(0);
            $table->string('accrual_method'); // periodic, on_invoice
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->decimal('minimum_purchase', 15, 4)->nullable();
            $table->decimal('maximum_rebate', 15, 4)->nullable();
            $table->unsignedBigInteger('accrual_account_id')->nullable();
            $table->unsignedBigInteger('expense_account_id')->nullable();
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('accrual_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('expense_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null');

            $table->index(['organization_id', 'contact_id', 'status']);
            $table->index(['organization_id', 'valid_from', 'valid_to']);
        });

        Schema::create('rebate_accruals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('rebate_master_id');
            $table->unsignedBigInteger('invoice_id');
            $table->date('accrual_date');
            $table->decimal('invoice_amount', 15, 4);
            $table->decimal('rebate_amount', 15, 4);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('status')->default('pending'); // pending, posted, settled
            $table->timestamps();

            $table->foreign('rebate_master_id')->references('id')->on('rebate_masters')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');

            $table->index(['rebate_master_id', 'status']);
            $table->index(['invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rebate_accruals');
        Schema::dropIfExists('rebate_masters');
    }
};
