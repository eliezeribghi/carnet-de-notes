<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\User;
use App\Services\Pricing\PriceResolver;
use PHPUnit\Framework\TestCase;

class PriceResolverTest extends TestCase
{
    public function test_it_returns_retail_price_for_guest(): void
    {
        $product = new Product([
            'price_retail_cents' => 1249,
            'price_pro_cents' => 1099,
        ]);

        $resolver = new PriceResolver();

        $result = $resolver->resolve($product, null);

        $this->assertSame('retail', $result['price_type']);
        $this->assertSame(1249, $result['price_cents']);
        $this->assertSame(12.49, $result['price']);
    }

    public function test_it_returns_pro_price_for_pro_user(): void
    {
        $product = new Product([
            'price_retail_cents' => 1249,
            'price_pro_cents' => 1099,
        ]);

        $user = new class extends User {
            public function canAccessProPricing(): bool
            {
                return true;
            }
        };

        $resolver = new PriceResolver();

        $result = $resolver->resolve($product, $user);

        $this->assertSame('pro', $result['price_type']);
        $this->assertSame(1099, $result['price_cents']);
        $this->assertSame(10.99, $result['price']);
    }
}
