<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_close_periods', function (Blueprint $table): void {
            if (! Schema::hasColumn('financial_close_periods', 'signed_off_by')) {
                $table->unsignedBigInteger('signed_off_by')->nullable()->after('closed_by');
                $table->foreign('signed_off_by', 'fk_fcp_signoff_user')
                    ->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('financial_close_periods', 'signed_off_at')) {
                $table->dateTime('signed_off_at')->nullable()->after('signed_off_by');
            }
            if (! Schema::hasColumn('financial_close_periods', 'sign_off_notes')) {
                $table->text('sign_off_notes')->nullable()->after('signed_off_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('financial_close_periods', function (Blueprint $table): void {
            if (Schema::hasColumn('financial_close_periods', 'signed_off_by')) {
                $table->dropForeign('fk_fcp_signoff_user');
                $table->dropColumn('signed_off_by');
            }
            if (Schema::hasColumn('financial_close_periods', 'signed_off_at')) {
                $table->dropColumn('signed_off_at');
            }
            if (Schema::hasColumn('financial_close_periods', 'sign_off_notes')) {
                $table->dropColumn('sign_off_notes');
            }
        });
    }
};
