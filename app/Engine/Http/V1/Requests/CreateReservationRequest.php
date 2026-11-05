<?php

namespace App\Engine\Http\V1\Requests;

use App\Engine\Application\ReservationInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Schema ReservationRequest della spec.
 */
final class CreateReservationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quote_id' => ['required', 'uuid'],
            'customer' => ['required', 'array'],
            'customer.first_name' => ['required', 'string', 'max:100'],
            'customer.last_name' => ['required', 'string', 'max:100'],
            'customer.email' => ['required', 'string', 'email', 'max:255'],
            'customer.phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ().\-]{6,30}$/'],
            'vehicle' => ['required', 'array'],
            'vehicle.plate' => ['required', 'string', 'max:15', 'regex:/^[A-Za-z0-9 \-]+$/'],
            'vehicle.make' => ['nullable', 'string', 'max:50'],
            'vehicle.model' => ['nullable', 'string', 'max:50'],
            'travel' => ['required', 'array'],
            'travel.outbound_flight' => ['nullable', 'string', 'max:20'],
            'travel.return_flight' => ['nullable', 'string', 'max:20'],
            'travel.passengers' => ['required', 'integer', 'between:1,9'],
            'billing' => ['nullable', 'array'],
            'billing.name' => ['required_with:billing', 'string', 'max:255'],
            'billing.vat_number' => ['nullable', 'string', 'max:20'],
            'billing.tax_code' => ['nullable', 'string', 'max:20'],
            'billing.address' => ['nullable', 'string', 'max:255'],
            'billing.postal_code' => ['nullable', 'string', 'max:10'],
            'billing.city' => ['nullable', 'string', 'max:100'],
            'billing.province' => ['nullable', 'string', 'size:2'],
            'billing.country' => ['required_with:billing', 'string', 'size:2'],
            'billing.sdi_code' => ['nullable', 'string', 'max:7'],
            'billing.pec' => ['nullable', 'string', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'referral' => ['nullable', 'string', 'max:50'],
            'consents' => ['required', 'array'],
            'consents.privacy' => ['required', 'accepted'],
            'consents.marketing' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Il campo è obbligatorio.',
            'required_with' => 'Il campo è obbligatorio.',
            'string' => 'Il campo deve essere un testo.',
            'array' => 'Il campo deve essere un oggetto.',
            'max' => 'Il campo può avere al massimo :max caratteri.',
            'size' => 'Il campo deve avere :size caratteri.',
            'email' => "L'indirizzo email non è valido.",
            'quote_id.uuid' => 'Il preventivo non è valido.',
            'customer.phone.regex' => 'Il numero di telefono non è valido.',
            'vehicle.plate.regex' => 'La targa può contenere solo lettere, cifre, spazi e trattini.',
            'travel.passengers.integer' => 'Il numero di passeggeri deve essere un intero.',
            'travel.passengers.between' => 'I passeggeri devono essere da 1 a 9.',
            'consents.privacy.required' => 'Il consenso privacy è obbligatorio.',
            'consents.privacy.accepted' => 'Il consenso privacy è obbligatorio.',
            'consents.marketing.boolean' => 'Il consenso marketing deve essere vero o falso.',
        ];
    }

    public function toInput(): ReservationInput
    {
        /** @var array{first_name: string, last_name: string, email: string, phone: string} $customer */
        $customer = $this->validated('customer');
        /** @var array{plate: string, make?: string|null, model?: string|null} $vehicle */
        $vehicle = $this->validated('vehicle');
        /** @var array{outbound_flight?: string|null, return_flight?: string|null, passengers: int} $travel */
        $travel = $this->validated('travel');
        /** @var array<string, mixed>|null $billing */
        $billing = $this->validated('billing');

        $travel['passengers'] = (int) $travel['passengers'];
        if (is_array($billing)) {
            $billing['country'] = strtoupper((string) $billing['country']);
        }

        return new ReservationInput(
            quoteId: (string) $this->validated('quote_id'),
            customer: $customer,
            vehicle: $vehicle,
            travel: $travel,
            billing: $billing,
            notes: $this->validated('notes'),
            referral: $this->validated('referral'),
            consents: [
                'privacy' => true,
                'marketing' => $this->boolean('consents.marketing'),
            ],
        );
    }
}
