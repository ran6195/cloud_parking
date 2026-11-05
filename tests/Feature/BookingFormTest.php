<?php

namespace Tests\Feature;

use App\Livewire\BookingForm;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\FakesEngineApi;
use Tests\TestCase;

/**
 * Il modulo della landing usa il motore solo via HTTP: qui le risposte sono simulate.
 */
class BookingFormTest extends TestCase
{
    use FakesEngineApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now('Europe/Rome')->setDate(2026, 9, 29)->setTime(10, 0));
        $this->configureEngine();

        $general = app(GeneralSettings::class);
        $general->phone = '051 000 0000';
        $general->save();
    }

    /**
     * @param  array<string, mixed>  $routes
     */
    private function fakeEngine(array $routes = []): void
    {
        Http::fake($routes + [self::ENGINE.'/extras' => Http::response($this->engineExtras())]);
    }

    private function datesFilled(): Testable
    {
        return Livewire::test(BookingForm::class)
            ->set('partenza', '2026-10-01T06:00')
            ->set('rientro', '2026-10-06T22:00')
            ->set('persone', '3');
    }

    private function detailsFilled(): Testable
    {
        return $this->datesFilled()
            ->call('calcola')
            ->call('avanti')
            ->set('nome', 'Maria')->set('cognome', 'Rossi')->set('email', 'maria@example.com')
            ->set('telefono', '+39 333 1234567')->set('targa', 'AB123CD')->set('privacy', true);
    }

    public function test_dates_are_validated_before_calling_the_engine(): void
    {
        $this->fakeEngine();

        Livewire::test(BookingForm::class)
            ->set('partenza', '2026-10-05T08:00')
            ->set('rientro', '2026-10-04T08:00')
            ->call('calcola')
            ->assertHasErrors(['rientro' => 'after'])
            ->assertSee('Il rientro deve essere dopo la partenza.');

        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/quotes'));
    }

    public function test_shows_the_extras_offered_by_the_engine(): void
    {
        $this->fakeEngine();

        Livewire::test(BookingForm::class)
            ->assertSee('Posto coperto')
            ->assertSee('+40%')
            ->assertSee('Tieni le chiavi')
            ->assertDontSee('Ricarica');
    }

    public function test_asks_the_engine_for_a_quote_with_dates_in_rome_time(): void
    {
        $this->fakeEngine([self::ENGINE.'/quotes' => Http::response($this->engineQuote(), 201)]);

        $this->datesFilled()
            ->set('servizi', ['covered_parking'])
            ->set('pagamento', 'on_site')
            ->set('codice', ' estate10 ')
            ->call('calcola')
            ->assertHasNoErrors()
            ->assertSet('quote.total.amount_cents', 4300)
            ->assertSee('6 giorni di sosta')
            ->assertSee('€ 43')
            ->assertSee('Prenota a questo prezzo');

        Http::assertSent(fn (Request $request) => $request->url() === self::ENGINE.'/quotes'
            && $request->hasHeader('Authorization', 'Bearer sk_test')
            && $request['start'] === '2026-10-01T06:00:00+02:00'
            && $request['end'] === '2026-10-06T22:00:00+02:00'
            && $request['extras'] === ['covered_parking']
            && $request['discount_code'] === 'estate10');
    }

    public function test_engine_validation_errors_appear_on_the_right_field(): void
    {
        $this->fakeEngine([self::ENGINE.'/quotes' => Http::response($this->engineProblem(422, 'validation_failed', 'Dati non validi', [
            'errors' => ['start' => ['La consegna non può essere nel passato.']],
        ]), 422, ['Content-Type' => 'application/problem+json'])]);

        $this->datesFilled()->call('calcola')
            ->assertHasErrors('partenza')
            ->assertSee('La consegna non può essere nel passato.');
    }

    public function test_unavailable_dates_offer_a_phone_call(): void
    {
        $this->fakeEngine([self::ENGINE.'/quotes' => Http::response($this->engineQuote([
            'bookable' => false,
            'availability' => ['available' => false, 'parking' => ['available' => false, 'reason' => 'capacity_full', 'unavailable_dates' => ['2026-10-02']]],
        ]), 201)]);

        $this->datesFilled()->call('calcola')
            ->assertSee('Per queste date non ci sono più posti')
            ->assertSee('Chiamaci per verificare')
            ->call('avanti')
            ->assertSet('step', 'dates');
    }

    public function test_changing_a_field_clears_the_previous_quote(): void
    {
        $this->fakeEngine([self::ENGINE.'/quotes' => Http::response($this->engineQuote(), 201)]);

        $this->datesFilled()->call('calcola')
            ->assertNotSet('quote', null)
            ->set('rientro', '2026-10-09T06:00')
            ->assertSet('quote', null);
    }

    public function test_books_and_shows_the_confirmation(): void
    {
        $this->fakeEngine([
            self::ENGINE.'/quotes' => Http::response($this->engineQuote(), 201),
            self::ENGINE.'/reservations' => Http::response($this->engineReservation(), 201),
        ]);

        $form = $this->detailsFilled()->set('voloAndata', 'FR1234')->call('prenota')
            ->assertHasNoErrors()
            ->assertSet('step', 'done')
            ->assertSee('Prenotazione confermata')
            ->assertSee('22171')
            ->assertSee('Paghi in parcheggio');

        Http::assertSent(fn (Request $request) => $request->url() === self::ENGINE.'/reservations'
            && $request->hasHeader('Idempotency-Key', $form->get('idempotencyKey'))
            && $request['quote_id'] === '0193c4d2-7b1e-7cc4-9a51-3f0d2a8e6b10'
            && $request['customer'] === ['first_name' => 'Maria', 'last_name' => 'Rossi', 'email' => 'maria@example.com', 'phone' => '+39 333 1234567']
            && $request['travel'] === ['outbound_flight' => 'FR1234', 'return_flight' => null, 'passengers' => 3]
            && $request['consents'] === ['privacy' => true, 'marketing' => false]);
    }

    public function test_customer_details_are_required(): void
    {
        $this->fakeEngine([self::ENGINE.'/quotes' => Http::response($this->engineQuote(), 201)]);

        $this->datesFilled()->call('calcola')->call('avanti')
            ->call('prenota')
            ->assertHasErrors(['nome', 'cognome', 'email', 'telefono', 'targa', 'privacy'])
            ->assertSee('Per prenotare serve il consenso al trattamento dei dati.');
    }

    public function test_a_changed_price_shows_the_new_quote(): void
    {
        $this->fakeEngine([
            self::ENGINE.'/quotes' => Http::response($this->engineQuote(), 201),
            self::ENGINE.'/reservations' => Http::response($this->engineProblem(409, 'quote_mismatch', 'Il prezzo è cambiato.', [
                'quote' => $this->engineQuote(['id' => '11111111-1111-4111-8111-111111111111', 'total' => ['amount_cents' => 4800]]),
            ]), 409),
        ]);

        $this->detailsFilled()->call('prenota')
            ->assertSet('step', 'details')
            ->assertSet('quote.id', '11111111-1111-4111-8111-111111111111')
            ->assertSee('Il prezzo è cambiato: controlla il nuovo totale')
            ->assertSee('€ 48');
    }

    public function test_an_expired_quote_is_renewed(): void
    {
        $this->fakeEngine([
            self::ENGINE.'/quotes' => Http::sequence()
                ->push($this->engineQuote(), 201)
                ->push($this->engineQuote(['id' => '22222222-2222-4222-8222-222222222222']), 201),
            self::ENGINE.'/reservations' => Http::response($this->engineProblem(409, 'quote_expired', 'Il preventivo è scaduto.'), 409),
        ]);

        $this->detailsFilled()->call('prenota')
            ->assertSet('step', 'details')
            ->assertSet('quote.id', '22222222-2222-4222-8222-222222222222')
            ->assertSee('l&#039;abbiamo rinnovato allo stesso prezzo', false);
    }

    public function test_dates_taken_in_the_meantime_go_back_to_the_first_step(): void
    {
        $this->fakeEngine([
            self::ENGINE.'/quotes' => Http::response($this->engineQuote(), 201),
            self::ENGINE.'/reservations' => Http::response($this->engineProblem(409, 'dates_unavailable', 'Le date selezionate non sono più disponibili.', ['unavailable_dates' => ['2026-10-02']]), 409),
        ]);

        $this->detailsFilled()->call('prenota')
            ->assertSet('step', 'dates')
            ->assertSet('quote', null)
            ->assertSee('Le date selezionate non sono più disponibili.');
    }

    public function test_online_payment_with_the_simulated_paypal(): void
    {
        $pending = $this->engineReservation(['status' => 'pending_payment', 'payment' => ['method' => 'online', 'status' => 'pending', 'due_by' => '2026-09-29T10:30:00+02:00']]);
        $paid = $this->engineReservation(['status' => 'paid', 'payment' => ['method' => 'online', 'status' => 'paid', 'paid_at' => '2026-09-29T10:05:00+02:00']]);
        $id = $pending['id'];

        $this->fakeEngine([
            self::ENGINE.'/quotes' => Http::response($this->engineQuote(['payment_method' => 'online']), 201),
            self::ENGINE.'/reservations' => Http::response($pending, 201),
            self::ENGINE."/reservations/{$id}/payments/paypal/orders" => Http::response(['order_id' => 'ORD-1', 'amount' => ['amount_cents' => 4300, 'currency' => 'EUR'], 'status' => 'CREATED'], 201),
            self::ENGINE."/reservations/{$id}/payments/paypal/capture" => Http::response($paid),
        ]);

        $this->detailsFilled()->set('pagamento', 'online')->call('calcola')->call('avanti')
            ->set('nome', 'Maria')->set('cognome', 'Rossi')->set('email', 'maria@example.com')
            ->set('telefono', '+39 333 1234567')->set('targa', 'AB123CD')->set('privacy', true)
            ->call('prenota')
            ->assertSee('Ultimo passo: il pagamento')
            ->assertSee('Il posto è tenuto per te fino alle 10:30')
            ->call('pagaSimulato')
            ->assertSet('reservation.status', 'paid')
            ->assertSee('Pagamento ricevuto');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/capture') && $request['order_id'] === 'ORD-1');
    }

    public function test_offers_a_phone_call_when_the_engine_is_down(): void
    {
        Http::fake([
            self::ENGINE.'/extras' => Http::response($this->engineExtras()),
            self::ENGINE.'/quotes' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->datesFilled()->call('calcola')
            ->assertSet('unavailable', true)
            ->assertSee('Prenota con una telefonata')
            ->assertSee('051 000 0000');
    }

    public function test_offers_a_phone_call_when_the_engine_is_not_configured(): void
    {
        config(['services.engine.key' => null]);

        $this->datesFilled()->call('calcola')->assertSet('unavailable', true);
    }
}
