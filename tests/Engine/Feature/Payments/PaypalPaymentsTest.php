<?php

use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Payments\PaymentGateway;
use App\Engine\Infrastructure\Payments\FakePaymentGateway;
use App\Engine\Mail\ReservationConfirmed;
use App\Engine\Models\Discount;
use App\Engine\Models\PaymentTransaction;
use App\Engine\Models\Reservation;
use App\Engine\Settings\EngineSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshEngineDatabase;
use Tests\Concerns\ValidatesOpenApi;
use Tests\Engine\Support\EngineApi;

uses(RefreshEngineDatabase::class, ValidatesOpenApi::class);

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    EngineApi::freezeClock();
    EngineApi::novemberPriceList();
    ['client' => $this->client, 'headers' => $this->headers] = EngineApi::client();
    $this->paypal = app(PaymentGateway::class);
});

/**
 * Prenotazione online in attesa di pagamento (37,80 €: 3 giorni con posto coperto).
 */
function pendingReservation(object $test, array $quote = []): string
{
    $quoteId = EngineApi::createQuote($test, $test->headers, $quote + ['payment_method' => 'online']);

    return $test->postJson('/api/v1/reservations', EngineApi::reservationBody($quoteId), $test->headers)
        ->assertCreated()
        ->assertJsonPath('status', 'pending_payment')
        ->json('id');
}

function orderUrl(string $reservationId): string
{
    return "/api/v1/reservations/{$reservationId}/payments/paypal/orders";
}

function captureUrl(string $reservationId): string
{
    return "/api/v1/reservations/{$reservationId}/payments/paypal/capture";
}

/**
 * @param  array<string, mixed>  $event
 */
function paypalWebhook(object $test, array $event, bool $validSignature = true): mixed
{
    $body = json_encode($event, JSON_THROW_ON_ERROR);

    return $test->call('POST', '/api/v1/webhooks/paypal', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYPAL_TRANSMISSION_SIG' => $validSignature ? FakePaymentGateway::signature($body) : 'firma-falsa',
    ], content: $body);
}

it('crea l\'ordine con il totale della prenotazione e lo riusa finché è valido', function () {
    $id = pendingReservation($this);

    $response = $this->postJson(orderUrl($id), [], $this->headers);

    $this->assertMatchesOpenApi($response, '/reservations/{reservationId}/payments/paypal/orders', 'post');
    $response->assertCreated()
        ->assertJsonPath('amount', ['amount_cents' => 3780, 'currency' => 'EUR'])
        ->assertJsonPath('status', 'CREATED');

    $this->postJson(orderUrl($id), [], $this->headers)->assertJsonPath('order_id', $response->json('order_id'));
    expect(PaymentTransaction::query()->count())->toBe(1);
});

it('incassa l\'ordine, segna la prenotazione come pagata e invia le email una volta sola', function () {
    $id = pendingReservation($this);
    $orderId = $this->postJson(orderUrl($id), [], $this->headers)->json('order_id');

    $response = $this->postJson(captureUrl($id), ['order_id' => $orderId], $this->headers);

    $this->assertMatchesOpenApi($response, '/reservations/{reservationId}/payments/paypal/capture', 'post');
    $response->assertOk()
        ->assertJsonPath('status', 'paid')
        ->assertJsonPath('payment.status', 'paid')
        ->assertJsonPath('payment.paid_at', '2026-11-05T12:00:00+01:00')
        ->assertJsonPath('notifications.email_sent', true);

    $transaction = PaymentTransaction::query()->sole();
    expect($transaction->status)->toBe('COMPLETED')
        ->and($transaction->capture_id)->toStartWith('FAKECAP')
        ->and(Reservation::query()->sole()->receipt_path)->not->toBeNull();

    $this->postJson(captureUrl($id), ['order_id' => $orderId], $this->headers)->assertOk()->assertJsonPath('status', 'paid');
    Mail::assertQueuedCount(1);
    Mail::assertQueued(ReservationConfirmed::class);
});

it('rifiuta il pagamento di prenotazioni non pagabili online', function (Closure $prepare, string $detail) {
    $id = $prepare($this);

    $response = $this->postJson(orderUrl($id), [], $this->headers);

    $this->assertMatchesOpenApi($response, '/reservations/{reservationId}/payments/paypal/orders', 'post');
    $response->assertConflict()->assertJsonPath('code', 'reservation_not_payable')->assertJsonPath('detail', $detail);
})->with([
    'da pagare in parcheggio' => [fn ($test) => $test->postJson('/api/v1/reservations', EngineApi::reservationBody(EngineApi::createQuote($test, $test->headers)), $test->headers)->json('id'), 'La prenotazione è da pagare in parcheggio.'],
    'tempo scaduto' => [function ($test) {
        $id = pendingReservation($test);
        EngineApi::freezeClock('2026-11-05 12:30:00 Europe/Rome');

        return $id;
    }, 'Il tempo per pagare online è scaduto: la prenotazione non è più valida.'],
    'già pagata' => [function ($test) {
        $id = pendingReservation($test);
        $test->postJson(captureUrl($id), ['order_id' => $test->postJson(orderUrl($id), [], $test->headers)->json('order_id')], $test->headers);

        return $id;
    }, 'La prenotazione è già pagata.'],
]);

it('rifiuta un ordine che appartiene a un\'altra prenotazione', function () {
    $first = pendingReservation($this);
    $second = pendingReservation($this, ['extras' => []]);
    $orderId = $this->postJson(orderUrl($first), [], $this->headers)->json('order_id');

    $response = $this->postJson(captureUrl($second), ['order_id' => $orderId], $this->headers);

    $this->assertMatchesOpenApi($response, '/reservations/{reservationId}/payments/paypal/capture', 'post');
    $response->assertConflict()->assertJsonPath('detail', 'L\'ordine non appartiene a questa prenotazione.');
});

it('non incassa un ordine non ancora approvato dal cliente', function () {
    $this->paypal->requireApproval();
    $id = pendingReservation($this);
    $orderId = $this->postJson(orderUrl($id), [], $this->headers)->json('order_id');

    $response = $this->postJson(captureUrl($id), ['order_id' => $orderId], $this->headers);

    $this->assertMatchesOpenApi($response, '/reservations/{reservationId}/payments/paypal/capture', 'post');
    $response->assertUnprocessable()
        ->assertJsonPath('code', 'payment_not_verified')
        ->assertJsonPath('detail', 'L\'ordine PayPal non è stato approvato dal cliente.');

    $this->paypal->approve($orderId);
    $this->postJson(captureUrl($id), ['order_id' => $orderId], $this->headers)->assertOk()->assertJsonPath('status', 'paid');
});

it('non conferma un pagamento con un importo diverso e lo registra', function () {
    Log::spy();
    $id = pendingReservation($this);
    $orderId = $this->postJson(orderUrl($id), [], $this->headers)->json('order_id');
    $this->paypal->captureAmount($orderId, 100);

    $this->postJson(captureUrl($id), ['order_id' => $orderId], $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('detail', 'L\'importo dell\'ordine PayPal non corrisponde a quello della prenotazione.');

    expect(Reservation::query()->sole()->status)->toBe(ReservationStatus::PendingPayment)
        ->and(PaymentTransaction::query()->sole()->status)->toBe('COMPLETED');
    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'importo diverso'))->once();
});

it('risponde 502 se PayPal non risponde, e si può ritentare', function () {
    $id = pendingReservation($this);
    $this->paypal->failNextCall();

    $response = $this->postJson(orderUrl($id), [], $this->headers);

    $this->assertMatchesOpenApi($response, '/reservations/{reservationId}/payments/paypal/orders', 'post');
    $response->assertStatus(502)->assertJsonPath('code', 'payment_provider_error');
    expect(PaymentTransaction::query()->count())->toBe(0);

    $this->postJson(orderUrl($id), [], $this->headers)->assertCreated();
});

it('accetta un pagamento in ritardo solo se il posto è ancora libero', function () {
    app(EngineSettings::class)->set('parking.total_spots', 1);
    $id = pendingReservation($this);
    $orderId = $this->postJson(orderUrl($id), [], $this->headers)->json('order_id');

    EngineApi::freezeClock('2026-11-05 12:40:00 Europe/Rome');
    $this->artisan('engine:expire-pending')->assertSuccessful();
    $this->postJson(captureUrl($id), ['order_id' => $orderId], $this->headers)->assertOk()->assertJsonPath('status', 'paid');

    $other = Reservation::factory()->status(ReservationStatus::PendingPayment, now()->toImmutable()->subMinute())->create([
        'payment_method' => 'online', 'starts_at' => now()->toImmutable()->addDays(20), 'ends_at' => now()->toImmutable()->addDays(22),
    ]);
    Reservation::factory()->between(
        $other->starts_at->setTimezone('Europe/Rome')->format('Y-m-d H:i'),
        $other->ends_at->setTimezone('Europe/Rome')->format('Y-m-d H:i'),
    )->create();
    $tx = $other->paymentTransactions()->create(['provider' => 'paypal', 'order_id' => 'FAKE-ALTRO', 'status' => 'CREATED', 'amount_cents' => 2500]);
    $this->paypal->approve('FAKE-ALTRO');

    $this->postJson(captureUrl($other->id), ['order_id' => $tx->order_id], $this->headers)
        ->assertConflict()
        ->assertJsonPath('detail', 'Il tempo per pagare è scaduto e le date non sono più disponibili.');
    expect($tx->fresh()?->status)->toBe('CREATED');
});

it('non mostra le prenotazioni di un altro canale', function () {
    $id = pendingReservation($this);

    $this->postJson(orderUrl($id), [], EngineApi::client('pwa')['headers'])->assertNotFound();
});

it('conferma subito una prenotazione online gratuita', function () {
    Discount::factory()->create(['code' => 'GRATIS', 'percentage' => 100]);
    $quoteId = EngineApi::createQuote($this, $this->headers, ['payment_method' => 'online', 'discount_code' => 'gratis']);

    $this->postJson('/api/v1/reservations', EngineApi::reservationBody($quoteId), $this->headers)
        ->assertCreated()
        ->assertJsonPath('status', 'paid')
        ->assertJsonPath('total.amount_cents', 0)
        ->assertJsonPath('payment.status', 'paid')
        ->assertJsonPath('payment.due_by', null);

    Mail::assertQueued(ReservationConfirmed::class);
});

it('webhook: rifiuta le firme non valide e ignora gli eventi sconosciuti', function () {
    paypalWebhook($this, ['event_type' => 'CHECKOUT.ORDER.APPROVED', 'resource' => ['id' => 'X']], validSignature: false)->assertStatus(400);
    paypalWebhook($this, ['event_type' => 'BILLING.PLAN.CREATED', 'resource' => []])->assertNoContent();
    paypalWebhook($this, ['event_type' => 'CHECKOUT.ORDER.APPROVED', 'resource' => ['id' => 'ORDINE-SCONOSCIUTO']])->assertNoContent();
});

it('webhook: un ordine approvato viene incassato anche se il client si è chiuso', function () {
    $id = pendingReservation($this);
    $orderId = $this->postJson(orderUrl($id), [], $this->headers)->json('order_id');

    paypalWebhook($this, ['event_type' => 'CHECKOUT.ORDER.APPROVED', 'resource' => ['id' => $orderId]])->assertNoContent();

    expect(Reservation::query()->sole()->status)->toBe(ReservationStatus::Paid);
    Mail::assertQueued(ReservationConfirmed::class);
});

it('webhook: un incasso completato conferma la prenotazione', function () {
    $id = pendingReservation($this);
    $orderId = $this->postJson(orderUrl($id), [], $this->headers)->json('order_id');

    paypalWebhook($this, ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => [
        'id' => 'CAP123', 'status' => 'COMPLETED',
        'amount' => ['currency_code' => 'EUR', 'value' => '37.80'],
        'supplementary_data' => ['related_ids' => ['order_id' => $orderId]],
    ]])->assertNoContent();

    $reservation = Reservation::query()->sole();
    expect($reservation->status)->toBe(ReservationStatus::Paid)
        ->and(PaymentTransaction::query()->sole()->capture_id)->toBe('CAP123');

    // Un secondo invio dello stesso evento non cambia nulla.
    paypalWebhook($this, ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => [
        'id' => 'CAP123', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'EUR', 'value' => '37.80'],
        'supplementary_data' => ['related_ids' => ['order_id' => $orderId]],
    ]])->assertNoContent();
    Mail::assertQueuedCount(1);
});

it('non usa il PayPal simulato in produzione', function () {
    app()->forgetInstance(PaymentGateway::class);
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => app(PaymentGateway::class))->toThrow(LogicException::class, 'PayPal simulato');
});
