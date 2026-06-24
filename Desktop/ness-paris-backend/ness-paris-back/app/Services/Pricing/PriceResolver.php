<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\User;

class PriceResolver
{
    public function resolve(Product $product, ?User $user = null): array
    {
        $canUseProPrice = $user?->canAccessProPricing() === true;

        if ($canUseProPrice && $product->price_pro_cents !== null) {
            $priceCents = (int) $product->price_pro_cents;
            $priceType = 'pro';
        } elseif ($product->price_retail_cents !== null) {
            $priceCents = (int) $product->price_retail_cents;
            $priceType = 'retail';
        } else {
            throw new \RuntimeException("Missing retail price for product #{$product->id}");
        }

        return [
            'price_type'  => $priceType,
            'price_cents' => $priceCents,
            'price'       => $priceCents / 100,
        ];
    }
}
