<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_show_returns_retail_price_for_guest(): void
    {
        $product = Product::factory()->create([
            'model_name' => 'Sweat à capuche sans zip',
            'display_name' => 'Sweat à capuche sans zip White XS',
            'price' => 29.90,
            'price_retail_cents' => 1249,
            'price_pro_cents' => 1099,
            'stock_quantity' => 100,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.price', 12.49)
            ->assertJsonPath('data.price_cents', 1249)
            ->assertJsonPath('data.price_type', 'retail');
    }

    public function test_product_show_returns_pro_price_for_authorized_user(): void
    {
        $product = Product::factory()->create([
            'price' => 29.90,
            'price_retail_cents' => 1249,
            'price_pro_cents' => 1099,
        ]);

        $user = new class extends User {
            public function canAccessProPricing(): bool
            {
                return true;
            }
        };

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.price', 10.99)
            ->assertJsonPath('data.price_cents', 1099)
            ->assertJsonPath('data.price_type', 'pro');
    }

    public function test_product_show_keeps_retail_if_pro_user_but_no_pro_price(): void
    {
        $product = Product::factory()->create([
            'price' => 29.90,
            'price_retail_cents' => 1249,
            'price_pro_cents' => null,
        ]);

        $user = new class extends User {
            public function canAccessProPricing(): bool
            {
                return true;
            }
        };

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.price', 12.49)
            ->assertJsonPath('data.price_cents', 1249)
            ->assertJsonPath('data.price_type', 'retail');
    }

    public function test_product_show_response_has_expected_structure(): void
    {
        $product = Product::factory()->create([
            'price_retail_cents' => 1249,
            'price_pro_cents' => 1099,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'model_name',
                    'display_name',
                    'sku',
                    'reference_code',
                    'barcode_value',
                    'ean13',
                    'price',
                    'price_cents',
                    'price_type',
                    'stock_quantity',
                    'slug',
                    'seo_title',
                    'seo_description',
                    'seo_canonical',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }
}
