<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenPoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // guarded by route middleware as well
    }

    public function rules(): array
    {
        return [
            'file' => ['required','file','mimes:xlsx,xls,csv','max:10240'],
        ];
    }
}
