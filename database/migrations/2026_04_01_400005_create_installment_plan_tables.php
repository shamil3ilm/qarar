<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Installment Payment Plans (SAP FI-AR/FI-AP — F-36 / F-59 installment splitting).
 *
 * installment_plans     — parent plan tied to an invoice or bill
 * installment_schedules — individual installment milestones with due dates / amounts
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            // Source document
            $table->string('document_type', 30);           // invoice | bill | sales_order
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            // Plan totals
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('total_amount', 18, 4);
            $table->decimal('total_paid', 18, 4)->default(0);
            $table->decimal('outstanding', 18, 4);         // total_amount - total_paid
            $table->integer('installment_count');
            // Status
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'document_type', 'document_id'], 'inst_plans_doc_idx');
            $table->index(['organization_id', 'contact_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('installment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('installment_plan_id');
            $table->integer('installment_number');         // 1, 2, 3 …
            $table->decimal('amount', 18, 4);
            $table->decimal('paid_amount', 18, 4)->default(0);
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue', 'waived'])->default('pending');
            $table->unsignedBigInteger('payment_id')->nullable();   // FK to payments_received / payments_made
            $table->string('payment_type', 50)->nullable();         // payment_received | payment_made
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['installment_plan_id', 'installment_number'], 'inst_sched_plan_num_uq');
            $table->index('due_date');
            $table->foreign('installment_plan_id')->references('id')->on('installment_plans')->cascadeOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_schedules');
        Schema::dropIfExists('installment_plans');
    }
};
