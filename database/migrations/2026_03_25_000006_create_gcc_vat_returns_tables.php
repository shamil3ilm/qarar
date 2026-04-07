<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_return_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->char('country_code', 3);
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'ready', 'submitted', 'accepted', 'amended'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->string('reference_number', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'vat_periods_org_id_idx');
            $table->index(['organization_id', 'country_code'], 'vat_periods_org_country_idx');
            $table->index(['organization_id', 'status'], 'vat_periods_org_status_idx');
            $table->index(['period_start', 'period_end'], 'vat_periods_dates_idx');
        });

        Schema::create('vat_return_boxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vat_return_period_id');
            $table->string('box_number', 10);
            $table->string('box_label', 200);
            $table->decimal('output_amount', 15, 4)->default(0);
            $table->decimal('input_amount', 15, 4)->default(0);
            $table->decimal('net_vat', 15, 4)->default(0);
            $table->timestamps();

            $table->index('vat_return_period_id', 'vat_boxes_period_id_idx');
            $table->unique(['vat_return_period_id', 'box_number'], 'vat_boxes_period_box_unq');

            $table->foreign('vat_return_period_id', 'vat_boxes_period_fk')
                ->references('id')
                ->on('vat_return_periods')
                ->onDelete('cascade');
        });

        Schema::create('vat_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->enum('transaction_type', ['sale', 'purchase', 'adjustment']);
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('tax_period');
            $table->decimal('taxable_amount', 15, 4)->default(0);
            $table->decimal('vat_amount', 15, 4)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->char('country_code', 3);
            $table->boolean('is_exempt')->default(false);
            $table->boolean('is_zero_rated')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'vat_txn_org_id_idx');
            $table->index(['organization_id', 'tax_period'], 'vat_txn_org_period_idx');
            $table->index(['organization_id', 'transaction_type'], 'vat_txn_org_type_idx');
            $table->index(['source_type', 'source_id'], 'vat_txn_source_idx');
            $table->index('country_code', 'vat_txn_country_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_transactions');
        Schema::dropIfExists('vat_return_boxes');
        Schema::dropIfExists('vat_return_periods');
    }
};
