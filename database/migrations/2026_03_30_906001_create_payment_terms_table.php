<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration
{
    public function up(): void
    {
        Schema::create('payment_terms', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->unsignedTinyInteger('net_days')
                ->default(30)
                ->comment('Payment due in N days');
            $table->unsignedTinyInteger('discount_days')
                ->default(0)
                ->comment('Days within which discount applies');
            $table->decimal('discount_pct', 5, 2)
                ->default(0)
                ->comment('Cash discount percentage');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_terms');
    }
};
