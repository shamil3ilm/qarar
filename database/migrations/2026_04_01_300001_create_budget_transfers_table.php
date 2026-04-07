<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('transfer_number')->unique();                // BT-2026-00001
            $table->unsignedBigInteger('from_budget_id');
            $table->unsignedBigInteger('from_budget_line_id');
            $table->unsignedBigInteger('to_budget_id');
            $table->unsignedBigInteger('to_budget_line_id');
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');                 // draft|submitted|approved|rejected|posted
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['from_budget_line_id']);
            $table->index(['to_budget_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_transfers');
    }
};
