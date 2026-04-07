<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_credit_limits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('current_exposure', 15, 2)->default(0);
            $table->decimal('available_credit', 15, 2)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->string('risk_category', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'contact_id'], 'cust_credit_org_contact_unique');
            $table->index(['organization_id', 'is_active'], 'cust_credit_org_active_idx');
            $table->index(['organization_id', 'risk_category'], 'cust_credit_org_risk_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credit_limits');
    }
};
