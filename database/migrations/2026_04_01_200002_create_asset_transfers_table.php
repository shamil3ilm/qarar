<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SAP ABUMN: Inter-company fixed asset transfers
        Schema::create('asset_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('transfer_number', 50);

            // Sending side
            $table->unsignedBigInteger('sending_organization_id');
            $table->foreign('sending_organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('fixed_asset_id');
            $table->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->cascadeOnDelete();

            // Receiving side
            $table->unsignedBigInteger('receiving_organization_id');
            $table->foreign('receiving_organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('receiving_asset_id')->nullable();
            $table->foreign('receiving_asset_id')->references('id')->on('fixed_assets')->nullOnDelete();

            // Transfer details
            $table->date('transfer_date');
            $table->enum('transfer_type', ['book_value', 'gross_value', 'negotiated_price'])->default('book_value');

            // Asset values at transfer date
            $table->decimal('gross_value', 18, 4)->comment('Original acquisition cost');
            $table->decimal('accumulated_depreciation', 18, 4)->default(0);
            $table->decimal('net_book_value', 18, 4)->comment('book value = gross - accum_dep');
            $table->decimal('transfer_price', 18, 4)->nullable()->comment('For negotiated_price type');
            $table->decimal('gain_loss_amount', 18, 4)->default(0);

            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->string('cancellation_reason', 500)->nullable();

            // GL journal references
            $table->unsignedBigInteger('sending_journal_id')->nullable();
            $table->foreign('sending_journal_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->unsignedBigInteger('receiving_journal_id')->nullable();
            $table->foreign('receiving_journal_id')->references('id')->on('journal_entries')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['sending_organization_id', 'transfer_number']);
            $table->index(['sending_organization_id', 'status']);
            $table->index(['receiving_organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_transfers');
    }
};
