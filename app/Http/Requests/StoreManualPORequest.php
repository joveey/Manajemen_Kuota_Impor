<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreManualPORequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return (bool) ($user?->can('po.create'));
    }

    public function rules(): array
    {
        return [
            'po_number' => ['required','string','max:50','unique:purchase_orders,po_doc'],
            'order_date' => ['required','date'],
            'product_model' => ['required','string','max:100'],
            'quantity' => ['required','integer','min:1'],
            'unit_price' => ['nullable','numeric','min:0'],
            'notes' => ['nullable','string','max:500'],
            'create_product' => ['nullable','boolean'],
        ];
    }
}
