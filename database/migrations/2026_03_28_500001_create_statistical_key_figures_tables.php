<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('statistical_key_figure_values');
        Schema::dropIfExists('statistical_key_figures');

        Schema::create('statistical_key_figures', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('unit_of_measure', 20);
            $table->enum('skf_type', ['fixed', 'total'])->default('total');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code'], 'skf_org_code_unq');
        });

        Schema::create('statistical_key_figure_values', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('skfv_org_fk');
            $table->foreignId('statistical_key_figure_id')
                ->constrained('statistical_key_figures')
                ->name('skfv_skf_fk');
            $table->foreignId('cost_center_id')
                ->nullable()
                ->constrained('cost_centers')
                ->name('skfv_cc_fk');
            $table->foreignId('profit_center_id')
                ->nullable()
                ->constrained('profit_centers')
                ->name('skfv_pc_fk');
            $table->unsignedTinyInteger('period'); // 1-12
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('value', 18, 4);
            $table->foreignId('posted_by')
                ->nullable()
                ->constrained('users')
                ->name('skfv_posted_by_fk');
            $table->timestamps();

            $table->unique(
                ['organization_id', 'statistical_key_figure_id', 'cost_center_id', 'profit_center_id', 'period', 'fiscal_year'],
                'skfv_unique_posting'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistical_key_figure_values');
        Schema::dropIfExists('statistical_key_figures');
    }
};
