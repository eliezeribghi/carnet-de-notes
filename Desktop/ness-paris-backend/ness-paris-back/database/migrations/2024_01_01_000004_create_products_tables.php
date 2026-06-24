<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('brand_id')->nullable();
            $table->unsignedTinyInteger('category_id')->nullable();
            $table->unsignedTinyInteger('gender_id')->nullable();
            $table->string('model_name', 150);
            $table->string('subtitle')->nullable();
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('composition', 200)->nullable();
            $table->json('sizes')->nullable();
            $table->decimal('base_price', 10, 2)->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('seo_canonical')->nullable();
            $table->json('seo_keywords')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('gender_id')->references('id')->on('genders')->nullOnDelete();
            $table->index(['brand_id', 'category_id']);
            $table->index('gender_id');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_group_id')->nullable()->constrained('product_groups')->nullOnDelete()->index();
            $table->unsignedTinyInteger('brand_id');
            $table->unsignedTinyInteger('category_id');
            $table->unsignedTinyInteger('gender_id');
            $table->unsignedSmallInteger('color_id');
            $table->unsignedSmallInteger('size_id');
            $table->enum('age_group', ['adult', 'kid', 'both'])->default('adult');
            $table->string('model_name', 150);
            $table->string('display_name', 200);
            $table->string('sku', 50)->unique();
            $table->string('reference_code', 50)->unique();
            $table->string('barcode_value', 50)->nullable();
            $table->char('ean13', 13)->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('price_retail_cents')->nullable();
            $table->unsignedInteger('price_pro_cents')->nullable();
            $table->unsignedInteger('weight_grams')->default(250);
            $table->integer('stock_quantity')->default(0);
            $table->string('slug', 160)->unique();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_canonical')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'category_id']);
            $table->index(['gender_id', 'color_id']);
        });

        Schema::create('product_group_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('size_id');
            $table->unique(['product_group_id', 'size_id']);
            $table->foreign('size_id')->references('id')->on('sizes')->cascadeOnDelete();
        });

        Schema::create('product_group_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_group_id')->constrained()->cascadeOnDelete();
            $table->string('tag', 80);
            $table->timestamps();
            $table->unique(['product_group_id', 'tag']);
            $table->index('tag');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['flat', 'model_women', 'model_men', 'detail'])->default('flat');
            $table->string('path', 500);
            $table->string('url', 500)->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'is_primary']);
            $table->index(['product_id', 'type']);
        });

        Schema::create('product_variant_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('tag', 80);
            $table->timestamps();
            $table->unique(['product_id', 'tag']);
            $table->index('tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_tags');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_group_tags');
        Schema::dropIfExists('product_group_sizes');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_groups');
    }
};
