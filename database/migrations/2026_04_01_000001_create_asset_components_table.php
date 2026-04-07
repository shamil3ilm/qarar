<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_components', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->string('component_number', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 15, 4);
            $table->decimal('salvage_value', 15, 4)->default(0);
            $table->decimal('useful_life_years', 8, 2)->default(0);
            $table->decimal('accumulated_depreciation', 15, 4)->default(0);
            $table->decimal('book_value', 15, 4)->default(0);
            $table->string('depreciation_method', 50)->nullable();
            $table->enum('status', ['active', 'retired', 'transferred'])->default('active');
            $table->date('retirement_date')->nullable();
            $table->decimal('retirement_amount', 15, 4)->nullable();
            $table->string('retirement_reason')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['fixed_asset_id', 'component_number']);
            $table->index(['organization_id', 'fixed_asset_id']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_components');
    }
};
