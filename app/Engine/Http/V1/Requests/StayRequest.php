<?php

namespace App\Engine\Http\V1\Requests;

use App\Engine\Domain\Pricing\Exceptions\InvalidStay;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\StayPeriod;
use App\Engine\Domain\Shared\Clock;
use App\Engine\Http\V1\Rules\DateTimeWithOffset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Periodo di sosta e servizi richiesti: base di availability e quotes.
 */
abstract class StayRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start' => ['required', 'string', new DateTimeWithOffset],
            'end' => ['required', 'string', new DateTimeWithOffset],
            'extras' => ['sometimes', 'array'],
            'extras.*' => ['string', 'distinct', Rule::enum(ExtraId::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Il campo è obbligatorio.',
            'string' => 'Il campo deve essere un testo.',
            'array' => 'Il campo deve essere un elenco.',
            'extras.*.distinct' => 'Il servizio è ripetuto.',
            'extras.*.enum' => 'Servizio sconosciuto.',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['start', 'end'])) {
                return;
            }

            try {
                $stay = $this->stay();
            } catch (InvalidStay $e) {
                $validator->errors()->add('end', $e->getMessage());

                return;
            }

            $tolerance = (int) config('engine.start_tolerance_minutes');
            if ($stay->start < app(Clock::class)->now()->modify("-{$tolerance} minutes")) {
                $validator->errors()->add('start', 'La consegna non può essere nel passato.');
            }
        }];
    }

    public function stay(): StayPeriod
    {
        return new StayPeriod(
            DateTimeWithOffset::parse($this->string('start')->value()) ?? throw InvalidStay::endNotAfterStart(),
            DateTimeWithOffset::parse($this->string('end')->value()) ?? throw InvalidStay::endNotAfterStart(),
        );
    }

    /**
     * @return list<ExtraId>
     */
    public function extras(): array
    {
        /** @var list<string> $extras */
        $extras = $this->input('extras', []);

        return array_map(ExtraId::from(...), $extras);
    }
}
