<?php

namespace App\Engine\Http\V1\Requests;

use App\Engine\Domain\Pricing\PaymentMethod;
use App\Engine\Domain\Pricing\QuoteRequest;
use Illuminate\Validation\Rule;

final class CreateQuoteRequest extends StayRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            'discount_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'payment_method.enum' => 'Metodo di pagamento non valido: online oppure on_site.',
            'discount_code.max' => 'Il codice sconto può avere al massimo 50 caratteri.',
        ];
    }

    public function toQuoteRequest(): QuoteRequest
    {
        $code = $this->input('discount_code');

        return new QuoteRequest(
            stay: $this->stay(),
            extras: $this->extras(),
            paymentMethod: PaymentMethod::from($this->string('payment_method')->value()),
            discountCode: is_string($code) && trim($code) !== '' ? $code : null,
        );
    }
}
