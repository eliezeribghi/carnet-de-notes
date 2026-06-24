<?php

namespace App\Services\Cart;

use App\Models\ClientCart;
use App\Models\ClientCartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Pricing\PriceResolver;
use Illuminate\Support\Collection;

class CartService
{
    public function __construct(
        private readonly PriceResolver $priceResolver
    ) {
    }

    public function getOrCreateCart(User $user): ClientCart
    {
        return ClientCart::firstOrCreate([
            'user_id' => $user->id,
        ]);
    }

public function resolveProductFromPayload(array $item): Product
{
    $product = null;

    $rawProductId = isset($item['productId'])
        ? trim((string) $item['productId'])
        : '';

    $rawSlug = isset($item['slug'])
        ? trim((string) $item['slug'])
        : '';

    $rawSku = isset($item['sku'])
        ? trim((string) $item['sku'])
        : '';

    if ($rawProductId !== '') {
        if (ctype_digit($rawProductId)) {
            $product = Product::find((int) $rawProductId);
        } else {
            $product = Product::where('slug', $rawProductId)->first();
        }
    }

    if (! $product && $rawSlug !== '') {
        $product = Product::where('slug', $rawSlug)->first();
    }

    if (! $product && $rawSku !== '') {
        $product = Product::where('sku', $rawSku)->first();
    }

    abort_if(! $product, 422, 'Produit introuvable.');

    return $product;
}
    public function buildCartItemPayload(Product $product, array $input, User $user): array
    {
        $pricing = $this->priceResolver->resolve($product, $user);

        return [
            'product_id'   => $product->id,
            'sku'          => $product->sku,
            'name'         => $product->display_name,
            'color'        => data_get($product, 'color.display_name', $input['color'] ?? ''),
            'size'         => data_get($product, 'size.code', $input['size'] ?? ''),
            'price_cents'  => (int) $pricing['price_cents'],
            'price_type'   => $pricing['price_type'],
            'quantity'     => (int) $input['quantity'],
            'image'        => $input['image'] ?? '',
            'slug'         => $product->slug ?? '',
        ];
    }

    public function repriceCart(ClientCart $cart, User $user): ClientCart
    {
        $cart->load('items');

        foreach ($cart->items as $item) {
            $product = Product::where('sku', $item->sku)->first();

            if (! $product) {
                continue;
            }

            $pricing = $this->priceResolver->resolve($product, $user);

            $newPriceCents = (int) $pricing['price_cents'];
            $newPriceType  = $pricing['price_type'];

            if (
                (int) $item->price_cents !== $newPriceCents ||
                (string) $item->price_type !== (string) $newPriceType
            ) {
                $item->update([
                    'price_cents' => $newPriceCents,
                    'price_type'  => $newPriceType,
                ]);
            }
        }

        return $cart->fresh('items');
    }

    public function formatItems(ClientCart $cart): array
    {
        return $cart->items->map(function (ClientCartItem $item) {
            return [
                'cartId'      => $item->cart_item_id,
                'productId'   => $item->product_id,
                'sku'         => $item->sku,
                'name'        => $item->name,
                'color'       => $item->color,
                'size'        => $item->size,
                'price'       => ((int) $item->price_cents) / 100,
                'price_cents' => (int) $item->price_cents,
                'price_type'  => $item->price_type,
                'quantity'    => (int) $item->quantity,
                'image'       => $item->image,
                'slug'        => $item->slug,
                'addedAt'     => optional($item->created_at)?->timestamp
                    ? $item->created_at->timestamp * 1000
                    : null,
            ];
        })->toArray();
    }

    public function syncItems(ClientCart $cart, array $items, User $user): ClientCart
    {
        $keptIds = [];

        foreach ($items as $item) {
            $product = $this->resolveProductFromPayload($item);

            $payload = $this->buildCartItemPayload($product, $item, $user);

            $cartItem = ClientCartItem::updateOrCreate(
                [
                    'cart_id'      => $cart->id,
                    'cart_item_id' => $item['cartId'],
                ],
                $payload
            );

            $keptIds[] = $cartItem->cart_item_id;
        }

        $cart->items()->whereNotIn('cart_item_id', $keptIds)->delete();

        return $this->repriceCart($cart->fresh('items'), $user);
    }

    public function addItem(ClientCart $cart, array $validated, User $user): ClientCart
    {
        $product = $this->resolveProductFromPayload($validated);

        $existing = ClientCartItem::where('cart_id', $cart->id)
            ->where('cart_item_id', $validated['cartId'])
            ->first();

        if ($existing) {
            $existing->increment('quantity', (int) $validated['quantity']);

            $pricing = $this->priceResolver->resolve($product, $user);

            $existing->update([
                'price_cents' => (int) $pricing['price_cents'],
                'price_type'  => $pricing['price_type'],
            ]);
        } else {
            ClientCartItem::create([
                'cart_id'      => $cart->id,
                'cart_item_id' => $validated['cartId'],
                ...$this->buildCartItemPayload($product, $validated, $user),
            ]);
        }

        return $this->repriceCart($cart->fresh('items'), $user);
    }

    public function updateQuantity(ClientCart $cart, string $cartItemId, int $quantity, User $user): ClientCart
    {
        ClientCartItem::where('cart_id', $cart->id)
            ->where('cart_item_id', $cartItemId)
            ->update([
                'quantity' => $quantity,
            ]);

        return $this->repriceCart($cart->fresh('items'), $user);
    }

    public function removeItem(ClientCart $cart, string $cartItemId, User $user): ClientCart
    {
        ClientCartItem::where('cart_id', $cart->id)
            ->where('cart_item_id', $cartItemId)
            ->delete();

        return $this->repriceCart($cart->fresh('items'), $user);
    }

    public function clear(ClientCart $cart): void
    {
        $cart->items()->delete();
    }

    public function validateCheckoutCart(ClientCart $cart, User $user): ClientCart
    {
        $cart = $this->repriceCart($cart, $user);

        $cartItems = $cart->items;

        if ($cartItems->isEmpty()) {
            abort(422, 'Le panier est vide.');
        }

        foreach ($cartItems as $cartItem) {
            $product = Product::where('sku', $cartItem->sku)->first();

            if (! $product) {
                abort(422, "Le produit {$cartItem->name} n'existe plus.");
            }

            if ((int) $product->stock_quantity < (int) $cartItem->quantity) {
                abort(422, "Stock insuffisant pour {$cartItem->name}.");
            }
        }

        return $cart->fresh('items');
    }

    public function buildStripeLineItems(Collection $cartItems): array
    {
        return $cartItems->map(fn ($item) => [
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => (int) $item->price_cents,
                'product_data' => [
                    'name' => $item->name . ' — ' . $item->size . ' / ' . $item->color,
                ],
            ],
            'quantity' => (int) $item->quantity,
        ])->values()->toArray();
    }
}
