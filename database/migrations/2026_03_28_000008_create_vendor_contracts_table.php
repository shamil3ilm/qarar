<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('contract_number', 50)->nullable();
            $table->foreignId('contact_id')->constrained('contacts');
            $table->string('title', 200)->nullable();
            $table->enum('contract_type', ['supply', 'service', 'framework', 'blanket_order'])->default('supply');
            $table->enum('status', ['draft', 'active', 'expired', 'terminated'])->default('draft');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('signed_at')->nullable();
            $table->date('terminated_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->string('payment_terms', 200)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'vendor_contracts_org_status_idx');
            $table->index(['organization_id', 'contact_id'], 'vendor_contracts_org_contact_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_contracts');
    }
};
