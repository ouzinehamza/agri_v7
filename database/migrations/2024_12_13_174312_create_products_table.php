<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->unsignedBigIntegre('business_id');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->enum('type', ['single', 'variable']);
            $table->unsignedBigIntegre('unit_id');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
            $table->unsignedBigIntegre('brand_id')->nullable();
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            $table->unsignedBigIntegre('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->unsignedBigIntegre('sub_category_id')->nullable();
            $table->foreign('sub_category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->unsignedBigIntegre('tax')->nullable();
            $table->foreign('tax')->references('id')->on('tax_rates');
            $table->enum('tax_type', ['inclusive', 'exclusive']);
            $table->boolean('enable_stock')->default(0);
            $table->decimal('alert_quantity', 22, 4)->default(0);
            $table->string('sku');
            $table->enum('barcode_type', ['C39', 'C128', 'EAN-13', 'EAN-8', 'UPC-A', 'UPC-E', 'ITF-14']);
            $table->unsignedBigIntegre('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();

            //Indexing
            $table->index('name');
            $table->index('business_id');
            $table->index('unit_id');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
