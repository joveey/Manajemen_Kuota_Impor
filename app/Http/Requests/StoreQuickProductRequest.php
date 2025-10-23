<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuickProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->user()?->can('product.create');
    }

    public function rules(): array
    {
        return [
            'model' => ['required','string','max:100'],
            'hs_code' => ['required','string','max:50'],
            'pk_capacity' => ['nullable','numeric','min:0'],
            'category' => ['nullable','string','max:100'],
            'period_key' => ['nullable','string','max:50'],
            'return' => ['nullable','url'],
        ];
    }
}

