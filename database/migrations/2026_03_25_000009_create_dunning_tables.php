<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dunning_levels', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->tinyInteger('level_number')->unsigned();
            $table->string('name', 100);
            $table->smallInteger('days_overdue_from')->unsigned();
            $table->smallInteger('days_overdue_to')->unsigned()->nullable();
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->decimal('dunning_fee', 15, 4)->default(0);
            $table->boolean('is_legal_action')->default(false);
            $table->foreignId('email_template_id')->nullable()->constrained('import_templates')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'level_number'], 'dunning_levels_org_level_unique');
            $table->index(['organization_id', 'is_active'], 'dunning_levels_org_active_idx');
        });

        Schema::create('dunning_runs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->date('run_date');
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->unsignedInteger('total_customers')->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'run_date'], 'dunning_runs_org_date_idx');
            $table->index(['organization_id', 'status'], 'dunning_runs_org_status_idx');
        });

        Schema::create('dunning_notices', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('dunning_run_id')->constrained('dunning_runs')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('dunning_level_id')->constrained('dunning_levels');
            $table->decimal('total_overdue', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('notice_date');
            $table->timestamp('sent_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed', 'blocked'])->default('pending');
            $table->string('blocking_reason', 200)->nullable();
            $table->timestamps();

            $table->index(['dunning_run_id', 'status'], 'dunning_notices_run_status_idx');
            $table->index(['contact_id', 'status'], 'dunning_notices_contact_status_idx');
        });

        Schema::create('dunning_notice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dunning_notice_id')->constrained('dunning_notices')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('invoice_number', 50);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('original_amount', 15, 4);
            $table->decimal('outstanding_amount', 15, 4);
            $table->smallInteger('days_overdue')->unsigned();
            $table->timestamps();

            $table->index(['dunning_notice_id'], 'dunning_notice_items_notice_idx');
            $table->index(['invoice_id'], 'dunning_notice_items_invoice_idx');
        });

        Schema::create('dunning_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->date('blocked_until')->nullable();
            $table->string('reason', 500);
            $table->foreignId('blocked_by')->constrained('users');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('release_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'contact_id'], 'dunning_blocks_org_contact_idx');
            $table->index(['organization_id', 'released_at'], 'dunning_blocks_org_released_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dunning_blocks');
        Schema::dropIfExists('dunning_notice_items');
        Schema::dropIfExists('dunning_notices');
        Schema::dropIfExists('dunning_runs');
        Schema::dropIfExists('dunning_levels');
    }
};
