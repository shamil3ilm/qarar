<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('zatca_branch_id')->nullable()->after('compliance_status');
            $table->string('zatca_onboarding_status')->nullable()->after('zatca_branch_id');
            $table->dateTime('zatca_certificate_expires_at')->nullable()->after('zatca_onboarding_status');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn([
                'zatca_branch_id',
                'zatca_onboarding_status',
                'zatca_certificate_expires_at',
            ]);
        });
    }
};
