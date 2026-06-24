<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientCart;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig,
                config('services.stripe.webhook_secret')
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type !== 'checkout.session.completed') {
            return response()->json(['status' => 'ignored'], 200);
        }

        try {
            StripeWebhookEvent::create([
                'eventid' => $event->id,
                'eventtype' => $event->type,
                'payload' => $payload,
                'processedat' => now(),
            ]);
        } catch (QueryException $e) {
            return response()->json(['status' => 'already_processed'], 200);
        }

        $session = $event->data->object;
        $meta = (array) ($session->metadata ?? []);
        $shipping = isset($meta['shipping']) ? json_decode($meta['shipping'], true) : null;

        if (!is_array($shipping)) {
            return response()->json(['status' => 'invalid_metadata'], 200);
        }

        $cart = ClientCart::find($meta['cart_id'] ?? null);
        $company = Company::find($meta['company_id'] ?? null);
        $client = User::find($meta['user_id'] ?? null);

        if (!$cart || !$company || !$client) {
            return response()->json(['status' => 'missing_context'], 200);
        }

        $cartItems = $cart->items()->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['status' => 'cart_empty'], 200);
        }

        DB::transaction(function () use ($cartItems, $shipping, $client, $company, $cart, $session, $meta) {
            $existingOrder = Order::where('stripe_checkout_session_id', $session->id)->first();

            if ($existingOrder) {
                return;
            }

            $subtotalCents = 0;
            $shippingCents = (int) ($shipping['shipping_cents'] ?? 0);

            $order = Order::create([
                'number' => $meta['order_number'] ?? ('STRIPE-'.$session->id),
                'status' => 'paid',
                'company_id' => $company->id,
                'customer_id' => $client->id,
                'customer_email' => $shipping['shipping_email'] ?? $client->email,
                'customer_name' => $shipping['shipping_name'] ?? $client->name,
                'customer_phone' => $shipping['shipping_phone'] ?? null,
                'customer_company' => $company->name,
                'shipping_address' => $shipping['shipping_address'] ?? null,
                'shipping_city' => $shipping['shipping_city'] ?? null,
                'shipping_zip' => $shipping['shipping_zip'] ?? null,
                'shipping_country' => $shipping['shipping_country'] ?? 'FR',
                'billing_name' => $shipping['billing_name'] ?? ($shipping['shipping_name'] ?? $client->name),
                'billing_email' => $shipping['billing_email'] ?? ($shipping['shipping_email'] ?? $client->email),
                'billing_vat_number' => $shipping['billing_vat_number'] ?? null,
                'billing_address' => $shipping['billing_address'] ?? ($shipping['shipping_address'] ?? null),
                'billing_line2' => $shipping['billing_line2'] ?? null,
                'billing_zip' => $shipping['billing_zip'] ?? ($shipping['shipping_zip'] ?? null),
                'billing_city' => $shipping['billing_city'] ?? ($shipping['shipping_city'] ?? null),
                'billing_country' => $shipping['billing_country'] ?? ($shipping['shipping_country'] ?? 'FR'),
                'currency' => 'EUR',
                'subtotal_cents' => 0,
                'shipping_cents' => 0,
                'total_cents' => 0,
                'stripe_checkout_session_id' => $session->id,
                'stripe_payment_intent_id' => $session->payment_intent ?? null,
                'paid_at' => now(),
                'notes' => $shipping['notes'] ?? null,
            ]);

            foreach ($cartItems as $cartItem) {
                $product = Product::where('sku', $cartItem->sku)->lockForUpdate()->first();

                if (!$product) {
                    continue;
                }

                $quantity = (int) $cartItem->quantity;
                $unitPriceCents = (int) $cartItem->price_cents;
                $lineTotalCents = $unitPriceCents * $quantity;

                $subtotalCents += $lineTotalCents;

                OrderLine::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'name_snapshot' => $cartItem->name,
                    'sku_snapshot' => $cartItem->sku,
                    'model_snapshot' => $cartItem->name,
                    'color_snapshot' => $cartItem->color,
                    'size_snapshot' => $cartItem->size,
                    'image_snapshot' => $cartItem->image,
                    'unit_price_cents' => $unitPriceCents,
                    'qty' => $quantity,
                    'line_total_cents' => $lineTotalCents,
                ]);

                $product->decrement('stock_quantity', $quantity);
            }

            $order->update([
                'subtotal_cents' => $subtotalCents,
                'shipping_cents' => $shippingCents,
                'total_cents' => $subtotalCents + $shippingCents,
            ]);

            $cart->items()->delete();
        });

        return response()->json(['status' => 'ok']);
    }




}
