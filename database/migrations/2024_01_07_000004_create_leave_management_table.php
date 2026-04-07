<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compensatory off (for working on holidays/weekends)
        Schema::create('compensatory_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('worked_date');
            $table->string('reason', 500);
            $table->decimal('days_earned', 3, 1)->default(1);
            $table->date('valid_until');
            $table->decimal('days_used', 3, 1)->default(0);
            $table->decimal('days_expired', 3, 1)->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected', 'used', 'expired'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('approved_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensatory_offs');
    }
};
