<?php

namespace App\Livewire;

use App\Services\PriceCalculator;
use App\Settings\GeneralSettings;
use App\Settings\ParkingSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

class BookingForm extends Component
{
    public const PEOPLE_OPTIONS = ['1' => '1', '2' => '2', '3' => '3', '4+' => '4 o più'];

    public string $partenza = '';

    public string $rientro = '';

    public string $persone = '2';

    public ?int $days = null;

    public ?float $price = null;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'partenza' => ['required', 'date', 'after_or_equal:now'],
            'rientro' => ['required', 'date', 'after:partenza'],
            'persone' => ['required', 'in:'.implode(',', array_keys(self::PEOPLE_OPTIONS))],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'partenza.required' => 'Indica quando lasci l\'auto.',
            'partenza.date' => 'La data di partenza non è valida.',
            'partenza.after_or_equal' => 'La partenza non può essere nel passato.',
            'rientro.required' => 'Indica quando rientri.',
            'rientro.date' => 'La data di rientro non è valida.',
            'rientro.after' => 'Il rientro deve essere dopo la partenza.',
            'persone.required' => 'Indica quante persone viaggiano.',
            'persone.in' => 'Scegli un numero di persone tra quelli proposti.',
        ];
    }

    public function updated(string $property): void
    {
        // Un preventivo mostrato deve sempre corrispondere ai dati nel modulo.
        if (in_array($property, ['partenza', 'rientro', 'persone'], true)) {
            $this->reset('days', 'price');
        }
    }

    public function calcola(ParkingSettings $parking): void
    {
        $this->validate();

        $quote = PriceCalculator::fromSettings($parking)->quote(
            Carbon::parse($this->partenza),
            Carbon::parse($this->rientro),
        );

        $this->days = $quote['days'];
        $this->price = $quote['price'];
    }

    protected function bookingUrl(ParkingSettings $parking): ?string
    {
        if (blank($parking->booking_url) || $this->price === null) {
            return null;
        }

        $separator = str_contains($parking->booking_url, '?') ? '&' : '?';

        return $parking->booking_url.$separator.http_build_query([
            'partenza' => $this->partenza,
            'rientro' => $this->rientro,
            'persone' => $this->persone,
        ]);
    }

    public function render(): View
    {
        $parking = app(ParkingSettings::class);

        return view('livewire.booking-form', [
            'parking' => $parking,
            'general' => app(GeneralSettings::class),
            'bookingUrl' => $this->bookingUrl($parking),
            'minDate' => now()->format('Y-m-d\TH:i'),
        ]);
    }
}
