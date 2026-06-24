<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class UpdateProductPricing extends Command
{
    protected $signature = 'products:update-pricing';
    protected $description = 'Met à jour les prix pro et publics des produits';

    public function handle(): int
    {
        $pricingMap = [
            'TSHIRT_ADULT' => ['pro' => 250, 'retail' => 400],
            'TSHIRT_KID'   => ['pro' => 220, 'retail' => 370],
            'POLO'         => ['pro' => 505, 'retail' => 655],
            'SWEAT_CREW'   => ['pro' => 650, 'retail' => 800],
            'HOODIE'       => ['pro' => 850, 'retail' => 1000],
            'JOGGER'       => ['pro' => 850, 'retail' => 1000],
            'ZIP_HOODIE'   => ['pro' => 1099, 'retail' => 1249],
            'FLEECE'       => ['pro' => 1099, 'retail' => 1249],
        ];

        $updated = 0;

        Product::query()->chunkById(100, function ($products) use ($pricingMap, &$updated) {
            foreach ($products as $product) {
                $type = $this->detectProductType($product);

                if (! $type || ! isset($pricingMap[$type])) {
                    $this->warn("Aucun mapping trouvé pour le produit #{$product->id} {$product->display_name}");
                    continue;
                }

                $product->update([
                    'price_pro_cents'    => $pricingMap[$type]['pro'],
                    'price_retail_cents' => $pricingMap[$type]['retail'],
                ]);

                $updated++;
                $this->info("Produit #{$product->id} mis à jour : {$product->display_name}");
            }
        });

        $this->info("Terminé. {$updated} produit(s) mis à jour.");

        return self::SUCCESS;
    }

    private function detectProductType(Product $product): ?string
    {
        $name = mb_strtolower($product->display_name . ' ' . $product->model_name);

        if (str_contains($name, 't-shirt enfant') || str_contains($name, 'tshirt enfant')) {
            return 'TSHIRT_KID';
        }

        if (str_contains($name, 't-shirt') || str_contains($name, 'tshirt')) {
            return 'TSHIRT_ADULT';
        }

        if (str_contains($name, 'polo')) {
            return 'POLO';
        }

        if (str_contains($name, 'sweat col rond')) {
            return 'SWEAT_CREW';
        }

        if (str_contains($name, 'sweat zippé') || str_contains($name, 'sweat zip') || str_contains($name, 'zip')) {
            return 'ZIP_HOODIE';
        }

        if (str_contains($name, 'polaire')) {
            return 'FLEECE';
        }

        if (str_contains($name, 'jogging') || str_contains($name, 'jogger')) {
            return 'JOGGER';
        }

        if (str_contains($name, 'hoodie') || str_contains($name, 'capuche')) {
            return 'HOODIE';
        }

        return null;
    }
}
