<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_advances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('advance_number', 30)->nullable();
            $table->foreignId('contact_id')->constrained('contacts');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('adjusted_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('exchange_rate', 10, 6)->default(1);
            $table->date('payment_date');
            $table->string('payment_method', 50)->nullable();
            $table->string('reference', 100)->nullable();
            $table->enum('status', ['paid', 'partially_adjusted', 'fully_adjusted', 'refunded'])->default('paid');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'contact_id'], 'vendor_adv_org_contact_idx');
            $table->index(['organization_id', 'status'], 'vendor_adv_org_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_advances');
    }
};
