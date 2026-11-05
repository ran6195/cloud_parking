<?php

namespace App\Engine\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Schema CancellationRequest. Il rimborso è obbligatorio solo per le prenotazioni pagate online:
 * lo verifica CancellationService, che conosce la prenotazione.
 */
final class CancelReservationRequest extends FormRequest
{
    /** Codice fiscale (anche con omocodia) oppure partita IVA. */
    private const TAX_CODE = '/^([A-Z]{6}[0-9LMNPQRSTUV]{2}[A-EHLMPR-T][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]|[0-9]{11})$/';

    protected function prepareForValidation(): void
    {
        $taxCode = $this->input('refund.tax_code');

        if (is_string($taxCode)) {
            $this->merge(['refund' => ['tax_code' => strtoupper((string) preg_replace('/\s+/', '', $taxCode))] + (array) $this->input('refund')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
            'refund' => ['nullable', 'array'],
            'refund.tax_code' => ['required_with:refund', 'string', 'regex:'.self::TAX_CODE],
            'refund.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.max' => 'Il motivo può avere al massimo 500 caratteri.',
            'refund.array' => 'I dati del rimborso non sono validi.',
            'refund.tax_code.required_with' => 'Per il rimborso serve il codice fiscale di chi ha pagato.',
            'refund.tax_code.regex' => 'Il codice fiscale o la partita IVA non sono validi.',
            'refund.notes.max' => 'Le note possono avere al massimo 500 caratteri.',
        ];
    }

    /**
     * @return array{tax_code: string, notes?: string|null}|null
     */
    public function refund(): ?array
    {
        /** @var array{tax_code: string, notes?: string|null}|null */
        return $this->validated('refund');
    }
}
