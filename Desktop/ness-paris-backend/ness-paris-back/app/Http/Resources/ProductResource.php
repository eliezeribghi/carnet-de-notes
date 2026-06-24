<?php

namespace App\Http\Resources;

use App\Services\Pricing\PriceResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pricing = app(PriceResolver::class)->resolve($this->resource, $request->user());

        return [
            'id'             => $this->id,
            'model_name'     => $this->model_name,
            'display_name'   => $this->display_name,

            'sku'            => $this->sku,
            'reference_code' => $this->reference_code,
            'barcode_value'  => $this->barcode_value,
            'ean13'          => $this->ean13,

            'price'          => $pricing['price'],
            'price_cents'    => $pricing['price_cents'],
            'price_type'     => $pricing['price_type'],

            'stock_quantity' => (int) $this->stock_quantity,

            'brand' => $this->whenLoaded('brand', function () {
                return [
                    'id'   => $this->brand?->id,
                    'name' => $this->brand?->name,
                    'city' => $this->brand?->city,
                ];
            }),

            'category' => $this->whenLoaded('category', function () {
                return [
                    'id'    => $this->category?->id,
                    'code'  => $this->category?->code,
                    'label' => $this->category?->label,
                ];
            }),

            'gender' => $this->whenLoaded('gender', function () {
                return [
                    'id'    => $this->gender?->id,
                    'code'  => $this->gender?->code,
                    'label' => $this->gender?->label,
                ];
            }),

            'color' => $this->whenLoaded('color', function () {
                return [
                    'id'           => $this->color?->id,
                    'key'          => $this->color?->key,
                    'display_name' => $this->color?->display_name,
                    'slug'         => $this->color?->slug,
                    'hex'          => $this->color?->hex,
                    'is_grey'      => (bool) $this->color?->is_grey,
                ];
            }),

            'size' => $this->whenLoaded('size', function () {
                return [
                    'id'       => $this->size?->id,
                    'code'     => $this->size?->code,
                    'label'    => $this->size?->label,
                    'segment'  => $this->size?->segment,
                    'order'    => $this->size?->sort_order,
                ];
            }),

            'slug'            => $this->slug,
            'seo_title'       => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_canonical'   => $this->seo_canonical,

            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
