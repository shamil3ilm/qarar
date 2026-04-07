<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('co_repostings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->string('reposting_number', 50);
            $table->unique(['organization_id', 'reposting_number']);

            $table->date('posting_date');
            $table->date('document_date');
            $table->unsignedTinyInteger('period');     // 1–12
            $table->unsignedSmallInteger('fiscal_year');

            // Polymorphic sender
            $table->enum('from_type', ['cost_center', 'internal_order', 'profit_center']);
            $table->unsignedBigInteger('from_id');

            // Polymorphic receiver
            $table->enum('to_type', ['cost_center', 'internal_order', 'profit_center']);
            $table->unsignedBigInteger('to_id');

            $table->foreignId('cost_element_id')->constrained('cost_elements');
            $table->decimal('amount', 15, 4);
            $table->char('currency_code', 3)->default('SAR');
            $table->text('narration')->nullable();

            $table->enum('status', ['posted', 'reversed'])->default('posted');
            $table->foreignId('reversed_by_id')->nullable()->constrained('co_repostings')->nullOnDelete();

            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('co_repostings');
    }
};
