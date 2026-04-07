<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('return_period', 20);
            $table->enum('return_type', ['GSTR-1', 'GSTR-2', 'GSTR-3B', 'GSTR-9', 'GSTR-9C'])->default('GSTR-1');
            $table->string('gstin', 20)->nullable();
            $table->decimal('total_taxable_value', 15, 2)->default(0);
            $table->decimal('total_cgst', 15, 2)->default(0);
            $table->decimal('total_sgst', 15, 2)->default(0);
            $table->decimal('total_igst', 15, 2)->default(0);
            $table->decimal('total_cess', 15, 2)->default(0);
            $table->enum('status', ['draft', 'filed', 'late_filed', 'cancelled'])->default('draft');
            $table->string('arn', 100)->nullable();
            $table->timestamp('filed_at')->nullable();
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'return_type', 'return_period'], 'gst_ret_org_type_period_idx');
            $table->index(['organization_id', 'status'], 'gst_ret_org_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_returns');
    }
};
