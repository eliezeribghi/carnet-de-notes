<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Pour l'instant : tout le monde peut lire le catalogue
        return true;
    }

    public function rules(): array
    {
        return [
            // Filtres simples
            'gender'     => ['nullable', 'string', 'max:30'],
            'category'   => ['nullable', 'string', 'max:50'],
            'color'      => ['nullable', 'string', 'max:50'],
            'size'       => ['nullable', 'string', 'max:20'],
            'search'     => ['nullable', 'string', 'max:100'],

            // Flags booléens
            'kid_only'   => ['nullable', 'boolean'],
            'adult_only' => ['nullable', 'boolean'],

            // Pagination
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page'       => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Pour convertir "1"/"0" en booléens Laravel.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'kid_only'   => filter_var($this->kid_only, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'adult_only' => filter_var($this->adult_only, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        ]);
    }
}
