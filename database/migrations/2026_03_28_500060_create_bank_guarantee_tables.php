<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bank_guarantees');

        Schema::create('bank_guarantees', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('guarantee_number', 50);
            $table->enum('guarantee_type', ['bid_bond', 'performance_bond', 'advance_payment', 'retention', 'financial'])
                ->default('performance_bond');
            $table->enum('direction', ['issued', 'received'])->default('issued');
            $table->foreignId('bank_id')->nullable()->constrained('contacts')->name('bg_bank_fk');
            $table->foreignId('beneficiary_id')->nullable()->constrained('contacts')->name('bg_beneficiary_fk');
            $table->foreignId('applicant_id')->nullable()->constrained('contacts')->name('bg_applicant_fk');
            $table->foreignId('related_purchase_order_id')->nullable()->constrained('purchase_orders')->name('bg_po_fk');
            $table->foreignId('related_sales_order_id')->nullable()->constrained('sales_orders')->name('bg_so_fk');
            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('amount', 18, 4);
            $table->decimal('bank_charges', 18, 4)->default(0);
            $table->date('issue_date');
            $table->date('expiry_date')->nullable();
            $table->date('claim_deadline')->nullable();
            $table->enum('status', ['draft', 'active', 'expired', 'claimed', 'returned', 'cancelled'])->default('draft');
            $table->boolean('is_auto_renewed')->default(false);
            $table->unsignedSmallInteger('renewal_period_days')->nullable();
            $table->decimal('claim_amount', 18, 4)->nullable();
            $table->date('claim_date')->nullable();
            $table->text('claim_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'guarantee_number'], 'bg_org_number_unq');
            $table->index(['organization_id', 'status'], 'bg_org_status_idx');
            $table->index(['expiry_date'], 'bg_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_guarantees');
    }
};
