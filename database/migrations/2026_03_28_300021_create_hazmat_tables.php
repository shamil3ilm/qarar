<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('product_hazmat_classifications');
        Schema::dropIfExists('hazmat_transport_regulations');
        Schema::dropIfExists('safety_data_sheet_sections');
        Schema::dropIfExists('safety_data_sheets');
        Schema::dropIfExists('hazmat_storage_compatibility_rules');
        Schema::dropIfExists('hazmat_storage_classes');
        Schema::dropIfExists('hazmat_classifications');

        Schema::create('hazmat_classifications', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('classification_system', 30); // ghs/un/adr/iata
            $table->string('code', 20);
            $table->string('name');
            $table->string('hazard_class', 50);
            $table->string('packing_group', 5)->nullable(); // I/II/III
            $table->string('signal_word', 20)->nullable(); // danger/warning
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id', 'hc_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->index(['classification_system', 'code'], 'hc_system_code_idx');
        });

        Schema::create('hazmat_storage_classes', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('code', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('max_quantity_kg', 10, 2)->nullable();
            $table->boolean('requires_ventilation')->default(false);
            $table->boolean('requires_grounding')->default(false);
            $table->string('fire_resistance_class', 10)->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'hsc_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
        });

        Schema::create('hazmat_storage_compatibility_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('storage_class_a_id');
            $table->unsignedBigInteger('storage_class_b_id');
            $table->boolean('is_compatible');
            $table->text('restriction_notes')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'hscr_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('storage_class_a_id', 'hscr_sc_a_fk')
                ->references('id')->on('hazmat_storage_classes')->onDelete('cascade');
            $table->foreign('storage_class_b_id', 'hscr_sc_b_fk')
                ->references('id')->on('hazmat_storage_classes')->onDelete('cascade');
        });

        Schema::create('safety_data_sheets', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('product_id');
            $table->string('sds_number', 50);
            $table->string('version', 20);
            $table->date('revision_date');
            $table->string('language_code', 5)->default('en');
            $table->string('supplier_name', 150)->nullable();
            $table->string('emergency_phone', 30)->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'sds_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('product_id', 'sds_product_fk')
                ->references('id')->on('products')->onDelete('cascade');

            $table->index(['product_id', 'is_current'], 'sds_product_current_idx');
        });

        Schema::create('safety_data_sheet_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('safety_data_sheet_id');
            $table->tinyInteger('section_number');
            $table->string('section_title', 100);
            $table->longText('content');
            $table->timestamps();

            $table->foreign('safety_data_sheet_id', 'sdss_sds_fk')
                ->references('id')->on('safety_data_sheets')->onDelete('cascade');
        });

        Schema::create('hazmat_transport_regulations', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('product_id');
            $table->string('un_number', 10)->nullable();
            $table->string('proper_shipping_name', 200)->nullable();
            $table->string('hazard_class', 20)->nullable();
            $table->string('packing_group', 5)->nullable();
            $table->string('transport_mode', 20); // road/air/sea/rail
            $table->boolean('is_forbidden')->default(false);
            $table->text('special_provisions')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'htr_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('product_id', 'htr_product_fk')
                ->references('id')->on('products')->onDelete('cascade');

            $table->index(['transport_mode', 'product_id'], 'htr_mode_product_idx');
        });

        Schema::create('product_hazmat_classifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('hazmat_classification_id');
            $table->unsignedBigInteger('storage_class_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('product_id', 'phc_product_fk')
                ->references('id')->on('products')->onDelete('cascade');
            $table->foreign('hazmat_classification_id', 'phc_classification_fk')
                ->references('id')->on('hazmat_classifications')->onDelete('cascade');
            $table->foreign('storage_class_id', 'phc_storage_class_fk')
                ->references('id')->on('hazmat_storage_classes')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_hazmat_classifications');
        Schema::dropIfExists('hazmat_transport_regulations');
        Schema::dropIfExists('safety_data_sheet_sections');
        Schema::dropIfExists('safety_data_sheets');
        Schema::dropIfExists('hazmat_storage_compatibility_rules');
        Schema::dropIfExists('hazmat_storage_classes');
        Schema::dropIfExists('hazmat_classifications');
    }
};
