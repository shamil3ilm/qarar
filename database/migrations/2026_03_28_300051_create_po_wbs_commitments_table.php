<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_wbs_commitments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained('purchase_order_lines')->cascadeOnDelete();
            $table->unsignedBigInteger('wbs_element_id');
            $table->decimal('committed_amount', 18, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('commitment_date');
            $table->string('status', 20)->default('open')
                ->comment('open/partially_delivered/closed');
            $table->timestamps();

            $table->index(['wbs_element_id', 'status'], 'po_wbs_comm_wbs_status_idx');
            $table->index('purchase_order_id', 'po_wbs_comm_po_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_wbs_commitments');
    }
};
