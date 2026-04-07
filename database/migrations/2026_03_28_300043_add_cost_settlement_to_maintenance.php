<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_order_cost_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('maintenance_order_id');
            $table->foreignId('cost_element_id')->nullable()->constrained('cost_elements')->nullOnDelete();
            $table->string('cost_type', 30)
                ->comment('labor/material/external/overhead');
            $table->decimal('quantity', 18, 4)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('total_cost', 18, 4);
            $table->string('currency_code', 3);
            $table->date('posting_date');
            $table->foreignId('vendor_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['maintenance_order_id', 'cost_type'], 'mo_cost_line_order_type_idx');
        });

        Schema::create('maintenance_order_settlements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('maintenance_order_id');
            $table->string('settlement_rule_type', 30)->comment('full/partial');
            $table->string('receiver_type', 30)->comment('cost_center/asset/order/wbs');
            $table->unsignedBigInteger('receiver_id');
            $table->decimal('percentage', 5, 2)->default(100);
            $table->decimal('settled_amount', 18, 4);
            $table->date('settlement_date');
            $table->smallInteger('fiscal_year');
            $table->tinyInteger('period');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['maintenance_order_id', 'settlement_date'], 'mo_settle_order_date_idx');
            $table->index(['receiver_type', 'receiver_id'], 'mo_settle_recv_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_order_settlements');
        Schema::dropIfExists('maintenance_order_cost_lines');
    }
};
