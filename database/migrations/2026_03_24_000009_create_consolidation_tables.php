<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consolidation_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('consolidation_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidation_group_id')->constrained('consolidation_groups')->cascadeOnDelete();
            $table->foreignId('entity_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('ownership_percent', 5, 2)->default(100.00);
            $table->enum('consolidation_method', ['full', 'proportional', 'equity'])->default('full');
            $table->string('local_currency', 3)->nullable();
            $table->timestamps();

            $table->unique(['consolidation_group_id', 'entity_organization_id'], 'consol_ent_group_entity_org_unique');
        });

        Schema::create('consolidation_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('consolidation_group_id')->constrained('consolidation_groups')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['open', 'in_progress', 'completed'])->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('consolidation_group_id');
        });

        Schema::create('elimination_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('consolidation_period_id')->constrained('consolidation_periods')->cascadeOnDelete();
            $table->enum('entry_type', [
                'intercompany_receivable',
                'intercompany_payable',
                'dividend',
                'investment',
                'other',
            ])->default('intercompany_receivable');
            $table->string('description');
            $table->foreignId('debit_account_id')->constrained('chart_of_accounts');
            $table->foreignId('credit_account_id')->constrained('chart_of_accounts');
            $table->decimal('amount', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'consolidation_period_id'], 'elim_entries_org_period_idx');
        });

        Schema::create('consolidated_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidation_period_id')
                ->constrained('consolidation_periods')
                ->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->foreignId('entity_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->decimal('local_amount', 15, 4);
            $table->decimal('exchange_rate', 10, 6)->default(1);
            $table->decimal('consolidated_amount', 15, 4);
            $table->timestamps();

            $table->unique(
                ['consolidation_period_id', 'account_id', 'entity_organization_id'],
                'consolidated_balances_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consolidated_balances');
        Schema::dropIfExists('elimination_entries');
        Schema::dropIfExists('consolidation_periods');
        Schema::dropIfExists('consolidation_entities');
        Schema::dropIfExists('consolidation_groups');
    }
};
