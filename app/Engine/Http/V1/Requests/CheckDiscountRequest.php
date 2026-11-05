<?php

namespace App\Engine\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CheckDiscountRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:50', 'regex:/\S/']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Il codice sconto è obbligatorio.',
            'code.string' => 'Il codice sconto deve essere un testo.',
            'code.max' => 'Il codice sconto può avere al massimo 50 caratteri.',
            'code.regex' => 'Il codice sconto è obbligatorio.',
        ];
    }
}
