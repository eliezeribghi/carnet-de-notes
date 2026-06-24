<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 100)->unique();
            $table->string('city', 100);
            $table->string('tagline')->nullable();
            $table->string('site_url')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('code', 50)->unique();
            $table->string('label', 100);
        });

        Schema::create('genders', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('code', 30)->unique();
            $table->string('label', 50);
            $table->string('barcode_prefix', 10)->nullable();
        });

        Schema::create('sizes', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 20)->unique();
            $table->string('label', 50);
            $table->enum('segment', ['ADULT', 'KID', 'UNISEX']);
            $table->smallInteger('sort_order');
            $table->unsignedInteger('barcode_index')->nullable();
            $table->timestamps();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('key', 50)->unique();
            $table->string('display_name', 50);
            $table->string('slug', 50)->unique();
            $table->unsignedInteger('barcode_index')->nullable();
            $table->char('hex', 7);
            $table->boolean('is_grey')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colors');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('genders');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
