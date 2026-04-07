<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('default_useful_life_years')->default(5);
            $table->enum('default_depreciation_method', [
                'straight_line',
                'declining_balance',
                'units_of_production',
                'sum_of_years_digits',
            ])->default('straight_line');
            $table->decimal('default_salvage_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('gl_asset_account_id')->nullable();
            $table->unsignedBigInteger('gl_depreciation_account_id')->nullable();
            $table->unsignedBigInteger('gl_accumulated_account_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('gl_asset_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('gl_depreciation_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('gl_accumulated_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('fixed_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('asset_category_id');
            $table->string('asset_number');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', [
                'active',
                'disposed',
                'written_off',
                'under_maintenance',
            ])->default('active');
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 15, 4);
            $table->decimal('salvage_value', 15, 4)->default(0);
            $table->decimal('useful_life_years', 5, 2);
            $table->enum('depreciation_method', [
                'straight_line',
                'declining_balance',
                'units_of_production',
                'sum_of_years_digits',
            ])->default('straight_line');
            $table->decimal('accumulated_depreciation', 15, 4)->default(0);
            $table->decimal('book_value', 15, 4);
            $table->date('last_depreciation_date')->nullable();
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_amount', 15, 4)->nullable();
            $table->string('disposal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('asset_category_id')->references('id')->on('asset_categories')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['organization_id', 'asset_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'asset_category_id']);
        });

        Schema::create('depreciation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('fiscal_year_id');
            $table->date('run_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', [
                'pending',
                'processing',
                'posted',
                'reversed',
            ])->default('pending');
            $table->unsignedInteger('total_assets')->default(0);
            $table->decimal('total_depreciation', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->restrictOnDelete();
            $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('depreciation_run_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('depreciation_run_id');
            $table->unsignedBigInteger('fixed_asset_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('opening_book_value', 15, 4);
            $table->decimal('depreciation_amount', 15, 4);
            $table->decimal('closing_book_value', 15, 4);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('depreciation_run_id')->references('id')->on('depreciation_runs')->cascadeOnDelete();
            $table->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->restrictOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();

            $table->index(['depreciation_run_id', 'fixed_asset_id']);
        });

        Schema::create('asset_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('fixed_asset_id');
            $table->enum('transaction_type', [
                'acquisition',
                'depreciation',
                'impairment',
                'revaluation',
                'partial_disposal',
                'full_disposal',
                'write_off',
                'transfer',
            ]);
            $table->date('transaction_date');
            $table->decimal('amount', 15, 4);
            $table->string('description');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->restrictOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'fixed_asset_id']);
            $table->index(['organization_id', 'transaction_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_transactions');
        Schema::dropIfExists('depreciation_run_lines');
        Schema::dropIfExists('depreciation_runs');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('asset_categories');
    }
};
