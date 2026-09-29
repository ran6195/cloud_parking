<?php

namespace Tests\Feature;

use App\Livewire\BookingForm;
use App\Settings\ParkingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 9, 29)->setTime(10, 0));

        $settings = app(ParkingSettings::class);
        $settings->price_up_to_3_days = 25;
        $settings->price_week = 45;
        $settings->price_two_weeks = 75;
        $settings->price_extra_day = 5;
        $settings->booking_url = 'https://prenota.example.com/nuova';
        $settings->save();
    }

    public function test_fields_are_required(): void
    {
        Livewire::test(BookingForm::class)
            ->set('partenza', '')
            ->set('rientro', '')
            ->call('calcola')
            ->assertHasErrors(['partenza' => 'required', 'rientro' => 'required'])
            ->assertSee('Indica quando lasci l\'auto.');
    }

    public function test_departure_cannot_be_in_the_past(): void
    {
        Livewire::test(BookingForm::class)
            ->set('partenza', '2026-09-28T08:00')
            ->set('rientro', '2026-10-02T08:00')
            ->call('calcola')
            ->assertHasErrors(['partenza' => 'after_or_equal'])
            ->assertSee('La partenza non può essere nel passato.');
    }

    public function test_return_must_be_after_departure(): void
    {
        Livewire::test(BookingForm::class)
            ->set('partenza', '2026-10-05T08:00')
            ->set('rientro', '2026-10-04T08:00')
            ->call('calcola')
            ->assertHasErrors(['rientro' => 'after'])
            ->assertSee('Il rientro deve essere dopo la partenza.');
    }

    public function test_people_must_be_one_of_the_options(): void
    {
        Livewire::test(BookingForm::class)
            ->set('partenza', '2026-10-01T08:00')
            ->set('rientro', '2026-10-03T08:00')
            ->set('persone', '9')
            ->call('calcola')
            ->assertHasErrors(['persone' => 'in']);
    }

    public function test_valid_dates_show_days_price_and_booking_link(): void
    {
        Livewire::test(BookingForm::class)
            ->set('partenza', '2026-10-01T06:00')
            ->set('rientro', '2026-10-06T22:00')
            ->set('persone', '3')
            ->call('calcola')
            ->assertHasNoErrors()
            ->assertSet('days', 6)
            ->assertSet('price', 45.0)
            ->assertSee('6 giorni di sosta')
            ->assertSee('€ 45')
            ->assertSee('Prenota a questo prezzo')
            ->assertSee('https://prenota.example.com/nuova?partenza=2026-10-01T06%3A00&amp;rientro=2026-10-06T22%3A00&amp;persone=3', false);
    }

    public function test_changing_a_field_clears_the_previous_quote(): void
    {
        Livewire::test(BookingForm::class)
            ->set('partenza', '2026-10-01T06:00')
            ->set('rientro', '2026-10-02T06:00')
            ->call('calcola')
            ->assertSet('days', 1)
            ->set('rientro', '2026-10-09T06:00')
            ->assertSet('days', null)
            ->assertSet('price', null);
    }
}
