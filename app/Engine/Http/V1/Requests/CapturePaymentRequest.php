<?php

namespace App\Engine\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CapturePaymentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['order_id' => ['required', 'string', 'max:64']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_id.required' => "L'identificativo dell'ordine PayPal è obbligatorio.",
            'order_id.string' => "L'identificativo dell'ordine PayPal deve essere un testo.",
            'order_id.max' => "L'identificativo dell'ordine PayPal è troppo lungo.",
        ];
    }
}
