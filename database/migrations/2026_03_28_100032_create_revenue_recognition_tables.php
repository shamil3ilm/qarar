<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('revenue_recognition_events');
        Schema::dropIfExists('performance_obligations');
        Schema::dropIfExists('revenue_contracts');

        Schema::create('revenue_contracts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('contract_number');
            $table->unsignedBigInteger('contact_id');
            $table->date('contract_date');
            $table->decimal('total_transaction_price', 15, 4)->default(0);
            $table->decimal('allocated_price', 15, 4)->default(0);
            $table->string('status')->default('draft'); // draft, active, completed, cancelled
            $table->string('recognition_method'); // point_in_time, over_time
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');

            $table->unique(['organization_id', 'contract_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'contact_id']);
        });

        Schema::create('performance_obligations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('revenue_contract_id');
            $table->string('description');
            $table->decimal('standalone_selling_price', 15, 4)->default(0);
            $table->decimal('allocated_transaction_price', 15, 4)->default(0);
            $table->string('recognition_method'); // point_in_time, over_time, milestone
            $table->string('status')->default('pending'); // pending, in_progress, completed
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->decimal('recognized_amount', 15, 4)->default(0);
            $table->decimal('deferred_amount', 15, 4)->default(0);
            $table->unsignedBigInteger('revenue_account_id')->nullable();
            $table->unsignedBigInteger('deferred_account_id')->nullable();
            $table->timestamps();

            $table->foreign('revenue_contract_id')->references('id')->on('revenue_contracts')->onDelete('cascade');
            $table->foreign('revenue_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('deferred_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null');

            $table->index(['revenue_contract_id', 'status']);
        });

        Schema::create('revenue_recognition_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('performance_obligation_id');
            $table->date('event_date');
            $table->decimal('amount_recognized', 15, 4);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('performance_obligation_id')->references('id')->on('performance_obligations')->onDelete('cascade');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['performance_obligation_id', 'event_date'], 'rev_rec_event_perf_oblig_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_recognition_events');
        Schema::dropIfExists('performance_obligations');
        Schema::dropIfExists('revenue_contracts');
    }
};
