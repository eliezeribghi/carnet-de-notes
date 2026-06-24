<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockRequest extends FormRequest
{
    public function authorize(): bool
    {

        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:0'],
            'operation' => ['required', 'string', 'in:set,increase,decrease'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'La quantité est obligatoire.',
            'quantity.integer'  => 'La quantité doit être un entier.',
            'quantity.min'      => 'La quantité ne peut pas être négative.',
            'operation.in'      => 'L\'opération doit être "set", "increase" ou "decrease".',
        ];
    }
}
