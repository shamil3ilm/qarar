<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_limits', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->decimal('credit_limit', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->smallInteger('payment_terms_days')->unsigned()->default(30);
            $table->enum('risk_class', ['low', 'medium', 'high', 'blocked'])->default('medium');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'contact_id'], 'credit_limits_org_contact_unique');
            $table->index(['organization_id', 'risk_class'], 'credit_limits_org_risk_idx');
        });

        Schema::create('credit_exposures', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->decimal('open_invoices', 15, 4)->default(0);
            $table->decimal('open_orders', 15, 4)->default(0);
            $table->decimal('total_exposure', 15, 4)->default(0);
            $table->decimal('credit_limit', 15, 4)->default(0);
            $table->decimal('available_credit', 15, 4)->default(0);
            $table->decimal('utilization_pct', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'contact_id', 'snapshot_date'], 'credit_exp_org_contact_date_uniq');
            $table->index(['organization_id', 'snapshot_date'], 'credit_exposures_org_date_idx');
            $table->index(['contact_id', 'snapshot_date'], 'credit_exposures_contact_date_idx');
        });

        Schema::create('credit_holds', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->timestamp('held_at');
            $table->timestamp('released_at')->nullable();
            $table->string('hold_reason', 500);
            $table->string('release_reason', 500)->nullable();
            $table->foreignId('held_by')->constrained('users');
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'contact_id'], 'credit_holds_org_contact_idx');
            $table->index(['organization_id', 'released_at'], 'credit_holds_org_released_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_holds');
        Schema::dropIfExists('credit_exposures');
        Schema::dropIfExists('credit_limits');
    }
};
