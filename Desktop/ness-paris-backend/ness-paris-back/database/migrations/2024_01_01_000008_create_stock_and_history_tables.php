<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete()->index();
            $table->enum('operation', ['add', 'remove']);
            $table->integer('quantity');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('sales_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete()->index();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->string('order_number', 100)->nullable()->index();
            $table->string('customer_name', 150)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('daily_stock_snapshot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete()->index();
            $table->integer('stock_quantity');
            $table->date('stock_date')->index();
            $table->timestamps();

            $table->unique(['product_id', 'stock_date'], 'unique_daily_stock');
        });

        Schema::create('sales_by_variant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete()->index();
            $table->unsignedSmallInteger('size_id');
            $table->unsignedSmallInteger('color_id');
            $table->unsignedTinyInteger('gender_id');
            $table->integer('total_sold')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'size_id', 'color_id', 'gender_id'], 'unique_variant');
            $table->foreign('size_id')->references('id')->on('sizes');
            $table->foreign('color_id')->references('id')->on('colors');
            $table->foreign('gender_id')->references('id')->on('genders');
        });

        Schema::create('print_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('mode', ['a4', 'etiquette', 'carton']);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Tables legacy vides (générées par erreur, conservées pour cohérence)
        Schema::create('stock_histories', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('sales_histories', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('sales_by_variants', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('daily_stock_snapshots', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stock_snapshots');
        Schema::dropIfExists('sales_by_variants');
        Schema::dropIfExists('sales_histories');
        Schema::dropIfExists('stock_histories');
        Schema::dropIfExists('print_history');
        Schema::dropIfExists('sales_by_variant');
        Schema::dropIfExists('daily_stock_snapshot');
        Schema::dropIfExists('sales_history');
        Schema::dropIfExists('stock_history');
    }
};
