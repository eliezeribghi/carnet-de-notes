<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('siret', 20)->nullable();
            $table->string('contact_name');
            $table->string('email')->unique()->index();
            $table->string('phone', 20)->nullable();
            $table->string('billing_address');
            $table->string('billing_city');
            $table->string('billing_zip', 10);
            $table->char('billing_country', 2)->default('FR');
            $table->string('shipping_address')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_zip', 10)->nullable();
            $table->char('shipping_country', 2)->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->enum('status', ['draft', 'pending_payment', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'])->default('draft')->index();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete()->index();
            $table->string('customer_email')->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('customer_company')->nullable();
            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_vat_number')->nullable();
            $table->string('shipping_address')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_zip', 10)->nullable();
            $table->char('shipping_country', 2)->default('FR');
            $table->string('billing_address')->nullable();
            $table->string('billing_line2')->nullable();
            $table->string('billing_zip')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_country')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('shipping_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->string('stripe_checkout_session_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('pennylane_invoice_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('refund_status')->nullable();
            $table->unsignedInteger('refunded_cents')->default(0);
            $table->text('notes')->nullable();
            $table->string('shipping_method_key')->nullable();
            $table->string('shipping_method_label')->nullable();
            $table->string('shipping_carrier')->nullable();
            $table->string('sendcloud_checkout_option_id')->nullable();
            $table->string('sendcloud_service_point_id')->nullable();
            $table->unsignedBigInteger('sendcloud_shipping_method_id')->nullable();
            $table->string('shipping_tracking_number')->nullable();
            $table->string('shipping_tracking_url')->nullable();
            $table->text('shipping_label_url')->nullable();
            $table->string('shipping_status')->nullable();
            $table->timestamps();
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete()->index();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete()->index();
            $table->string('name_snapshot');
            $table->string('sku_snapshot')->nullable();
            $table->string('model_snapshot')->nullable();
            $table->string('size_snapshot')->nullable();
            $table->string('color_snapshot')->nullable();
            $table->string('image_snapshot')->nullable();
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedSmallInteger('qty');
            $table->unsignedInteger('line_total_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
    }
};
