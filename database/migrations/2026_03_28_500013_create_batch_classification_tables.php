<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_classes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('class_code', 30);
            $table->string('class_name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'class_code'], 'bc_org_code_unq');
        });

        Schema::create('batch_characteristics', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'bchar_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('batch_class_id');
            $table->foreign('batch_class_id', 'bchar_class_fk')->references('id')->on('batch_classes');
            $table->string('characteristic_code', 30);
            $table->string('characteristic_name', 100);
            $table->enum('data_type', ['text', 'numeric', 'date', 'boolean'])->default('text');
            $table->string('unit_of_measure', 20)->nullable();
            $table->boolean('is_required')->default(false);
            $table->decimal('min_value', 18, 4)->nullable();
            $table->decimal('max_value', 18, 4)->nullable();
            $table->json('allowed_values')->nullable();
            $table->timestamps();

            $table->unique(['batch_class_id', 'characteristic_code'], 'bchar_class_code_unq');
        });

        Schema::create('batch_characteristic_values', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'bcv_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('inventory_batch_id');
            $table->foreign('inventory_batch_id', 'bcv_batch_fk')->references('id')->on('inventory_batches');
            $table->unsignedBigInteger('batch_characteristic_id');
            $table->foreign('batch_characteristic_id', 'bcv_char_fk')->references('id')->on('batch_characteristics');
            $table->string('text_value', 255)->nullable();
            $table->decimal('numeric_value', 18, 4)->nullable();
            $table->date('date_value')->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->timestamps();

            $table->unique(['inventory_batch_id', 'batch_characteristic_id'], 'bcv_batch_char_unq');
        });

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_class_id')->nullable()->after('metadata');
            $table->foreign('batch_class_id', 'ib_class_fk')->references('id')->on('batch_classes');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropForeign('ib_class_fk');
            $table->dropColumn('batch_class_id');
        });

        Schema::dropIfExists('batch_characteristic_values');
        Schema::dropIfExists('batch_characteristics');
        Schema::dropIfExists('batch_classes');
    }
};
