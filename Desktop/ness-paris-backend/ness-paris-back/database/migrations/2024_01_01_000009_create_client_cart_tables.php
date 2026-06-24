<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('client_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('client_carts')->cascadeOnDelete()->index();
            $table->string('cart_item_id');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete()->index();
            $table->string('sku');
            $table->string('name');
            $table->string('color')->default('');
            $table->string('size')->default('');
            $table->unsignedInteger('price_cents');
            $table->string('price_type', 20)->default('retail');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('image')->default('');
            $table->string('slug')->default('');
            $table->timestamps();

            $table->unique(['cart_id', 'cart_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_cart_items');
        Schema::dropIfExists('client_carts');
    }
};
