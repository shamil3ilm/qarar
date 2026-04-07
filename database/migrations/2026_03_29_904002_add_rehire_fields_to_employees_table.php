<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->date('rehire_date')->nullable()->after('termination_reason');
            $table->date('previous_termination_date')->nullable()->after('rehire_date');
            $table->unsignedTinyInteger('rehire_count')->default(0)->after('previous_termination_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn(['rehire_date', 'previous_termination_date', 'rehire_count']);
        });
    }
};
