<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class CreateReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Untuk sekarang, izinkan semua; middleware/permission dapat ditambahkan nanti
        return true;
    }

    public function rules(): array
    {
        return [
            'receipt_date' => ['required', 'date'],
            'quantity_received' => ['required', 'integer', 'min:1'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        // Kembalikan hanya field yang diizinkan
        $data = parent::validated();
        return Arr::only($data, [
            'receipt_date',
            'quantity_received',
            'document_number',
            'notes',
        ]);
    }
}

