<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_journal_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly', 'annually']);
            $table->unsignedTinyInteger('interval')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_run_date');
            $table->date('last_run_date')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedInteger('max_runs')->nullable();
            $table->unsignedBigInteger('debit_account_id');
            $table->unsignedBigInteger('credit_account_id');
            $table->decimal('amount', 15, 4);
            $table->char('currency_code', 3)->default('SAR');
            $table->text('narration')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('profit_center_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('debit_account_id')->references('id')->on('chart_of_accounts');
            $table->foreign('credit_account_id')->references('id')->on('chart_of_accounts');
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->nullOnDelete();
            $table->foreign('profit_center_id')->references('id')->on('profit_centers')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'is_active', 'next_run_date'], 'recurring_journal_templates_org_active_next_run_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_journal_templates');
    }
};
