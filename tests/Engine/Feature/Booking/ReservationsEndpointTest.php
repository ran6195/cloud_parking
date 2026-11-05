<?php

use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Mail\ReservationConfirmed;
use App\Engine\Mail\ReservationReceived;
use App\Engine\Models\Customer;
use App\Engine\Models\Discount;
use App\Engine\Models\Price;
use App\Engine\Models\Quote;
use App\Engine\Models\Reservation;
use App\Engine\Settings\EngineSettings;
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
});

it('crea una prenotazione da pagare in parcheggio, con email e ricevuta', function () {
    app(EngineSettings::class)->set('contact.email', 'gestore@example.com');
    config(['engine.cancellation_url' => 'https://sostablq.example/annulla/{token}']);
    $quoteId = EngineApi::createQuote($this, $this->headers);

    $response = $this->postJson('/api/v1/reservations', EngineApi::reservationBody($quoteId), $this->headers);

    $this->assertMatchesOpenApi($response, '/reservations', 'post');
    $reservation = Reservation::query()->with(['customer', 'lines'])->sole();

    $response->assertCreated()
        ->assertHeader('Location', '/api/v1/reservations/'.$reservation->id)
        ->assertJsonPath('status', 'confirmed')
        ->assertJsonPath('channel', 'web')
        ->assertJsonPath('number', $reservation->number)
        ->assertJsonPath('created_at', '2026-11-05T12:00:00+01:00')
        ->assertJsonPath('stay.billable_days', 3)
        ->assertJsonPath('stay.duration_minutes', 4260)
        ->assertJsonPath('lines.*.code', ['parking', 'covered_parking', 'on_site'])
        ->assertJsonPath('total.amount_cents', 3880)
        ->assertJsonPath('vehicle', ['plate' => 'AB123CD', 'make' => 'Fiat', 'model' => 'Panda'])
        ->assertJsonPath('payment', ['method' => 'on_site', 'status' => 'due_on_site', 'amount' => ['amount_cents' => 3880, 'currency' => 'EUR'], 'due_by' => null, 'paid_at' => null])
        ->assertJsonPath('cancellation', ['free_until' => '2026-11-19T08:00:00+01:00', 'cancelled_at' => null])
        ->assertJsonPath('notifications.email_sent', true);

    expect(Quote::query()->sole()->used_at)->not->toBeNull()
        ->and($reservation->quote_id)->toBe($quoteId)
        ->and($reservation->customer->email_normalized)->toBe('maria.rossi@example.com')
        ->and($reservation->lines)->toHaveCount(3)
        ->and($reservation->receipt_path)->toBe("engine/receipts/2026/{$reservation->number}.html")
        ->and($reservation->consents)->toBe(['privacy' => true, 'marketing' => false]);

    Storage::disk('local')->assertExists($reservation->receipt_path);
    Mail::assertQueued(ReservationConfirmed::class, fn (ReservationConfirmed $mail) => $mail->hasTo('maria.rossi@example.com')
        && $mail->cancellationUrl === 'https://sostablq.example/annulla/'.$reservation->cancellation_token);
    Mail::assertQueued(ReservationReceived::class, fn (ReservationReceived $mail) => $mail->hasTo('gestore@example.com'));
});

it('rende le email con il riepilogo della prenotazione', function () {
    $this->postJson('/api/v1/reservations', EngineApi::reservationBody(EngineApi::createQuote($this, $this->headers)), $this->headers)->assertCreated();
    $reservation = Reservation::query()->sole();

    $html = (new ReservationConfirmed($reservation, 'Sosta BLQ', '051 41 133 31', null))->render();

    expect($html)->toContain('Prenotazione confermata')
        ->toContain((string) $reservation->number)
        ->toContain('20/11/2026 08:00')
        ->toContain('38,80 €')
        ->toContain('051 41 133 31');
});

it('con il pagamento online la prenotazione attende il pagamento per 30 minuti, senza email', function () {
    $response = $this->postJson('/api/v1/reservations', EngineApi::reservationBody(EngineApi::createQuote($this, $this->headers, ['payment_method' => 'online'])), $this->headers);

    $this->assertMatchesOpenApi($response, '/reservations', 'post');
    $response->assertCreated()
        ->assertJsonPath('status', 'pending_payment')
        ->assertJsonPath('payment.status', 'pending')
        ->assertJsonPath('payment.due_by', '2026-11-05T12:30:00+01:00')
        ->assertJsonPath('notifications.email_sent', false)
        ->assertJsonPath('total.amount_cents', 3780);

    Mail::assertNothingQueued();
});

it('riusa il cliente con la stessa email e ne aggiorna i contatti', function () {
    $this->postJson('/api/v1/reservations', EngineApi::reservationBody(EngineApi::createQuote($this, $this->headers)), $this->headers)->assertCreated();
    $this->postJson('/api/v1/reservations', EngineApi::reservationBody(EngineApi::createQuote($this, $this->headers, ['extras' => []]), [
        'customer' => ['email' => 'MARIA.ROSSI@example.com', 'phone' => '+39 347 000 0000'],
    ]), $this->headers)->assertCreated();

    expect(Customer::query()->count())->toBe(1)
        ->and(Customer::query()->value('phone'))->toBe('+39 347 000 0000')
        ->and(Reservation::query()->count())->toBe(2);
});

it('non riusa un preventivo già usato, scaduto o di un altro client', function () {
    $quoteId = EngineApi::createQuote($this, $this->headers);
    $this->postJson('/api/v1/reservations', EngineApi::reservationBody($quoteId), $this->headers)->assertCreated();

    $used = $this->postJson('/api/v1/reservations', EngineApi::reservationBody($quoteId), $this->headers);
    $this->assertMatchesOpenApi($used, '/reservations', 'post');
    $used->assertConflict()->assertJsonPath('code', 'quote_used');

    $expiring = EngineApi::createQuote($this, $this->headers, ['extras' => []]);
    EngineApi::freezeClock('2026-11-05 12:31:00 Europe/Rome');
    $this->postJson('/api/v1/reservations', EngineApi::reservationBody($expiring), $this->headers)
        ->assertConflict()->assertJsonPath('code', 'quote_expired');

    $this->postJson('/api/v1/reservations', EngineApi::reservationBody($expiring), EngineApi::client('pwa')['headers'])
        ->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->postJson('/api/v1/reservations', EngineApi::reservationBody('0193c4d2-7b1e-7cc4-9a51-3f0d2a8e6b10'), $this->headers)
        ->assertNotFound();
});

it('propone un nuovo preventivo se il prezzo è cambiato', function () {
    $quoteId = EngineApi::createQuote($this, $this->headers);
    Price::query()->where('days', 3)->update(['amount_cents' => 3000]);

    $response = $this->postJson('/api/v1/reservations', EngineApi::reservationBody($quoteId), $this->headers);

    $this->assertMatchesOpenApi($response, '/reservations', 'post');
    $response->assertConflict()
        ->assertJsonPath('code', 'quote_mismatch')
        ->assertJsonPath('detail', 'Il prezzo è cambiato. Il nuovo preventivo è nel campo quote.')
        ->assertJsonPath('quote.status', 'active')
        ->assertJsonPath('quote.total.amount_cents', 4300);

    expect(Reservation::query()->count())->toBe(0)
        ->and(Quote::query()->count())->toBe(2)
        ->and(Quote::query()->find($quoteId)?->used_at)->toBeNull();
});

it('avvisa se il codice sconto non vale più', function () {
    Discount::factory()->create(['code' => 'UNO', 'percentage' => 10, 'max_uses' => 1]);
    $quoteId = EngineApi::createQuote($this, $this->headers, ['discount_code' => 'uno']);
    Reservation::factory()->create(['discount_id' => Discount::query()->value('id')]);

    $this->postJson('/api/v1/reservations', EngineApi::reservationBody($quoteId), $this->headers)
        ->assertConflict()
        ->assertJsonPath('code', 'quote_mismatch')
        ->assertJsonPath('detail', 'Il codice sconto non è più valido. Il nuovo preventivo è nel campo quote.')
        ->assertJsonPath('quote.discount.reason', 'exhausted');
});

it('rifiuta le date non più disponibili', function () {
    app(EngineSettings::class)->set('extras.covered_parking', ['capacity' => 1]);
    $quoteId = EngineApi::createQuote($this, $this->headers);
    Reservation::factory()->between('2026-11-21 08:00', '2026-11-22 08:00')->withExtra('covered_parking')->create();

    $response = $this->postJson('/api/v1/reservations', EngineApi::reservationBody($quoteId), $this->headers);

    $this->assertMatchesOpenApi($response, '/reservations', 'post');
    $response->assertConflict()
        ->assertJsonPath('code', 'dates_unavailable')
        ->assertJsonPath('unavailable_dates', ['2026-11-21'])
        ->assertJsonPath('detail', 'Le date selezionate non sono più disponibili. Contattaci al 051 41 133 31 per verificare la disponibilità residua.');

    expect(Quote::query()->sole()->used_at)->toBeNull();
});

it('valida i dati del cliente', function (array $overrides, string $field, string $message) {
    $response = $this->postJson('/api/v1/reservations', EngineApi::reservationBody(EngineApi::createQuote($this, $this->headers), $overrides), $this->headers);

    $this->assertMatchesOpenApi($response, '/reservations', 'post');
    $response->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
    expect($response->json('errors')[$field] ?? null)->toBe([$message]);
})->with([
    'privacy' => [['consents' => ['privacy' => false]], 'consents.privacy', 'Il consenso privacy è obbligatorio.'],
    'email' => [['customer' => ['email' => 'non-una-email']], 'customer.email', "L'indirizzo email non è valido."],
    'targa' => [['vehicle' => ['plate' => 'AB/123']], 'vehicle.plate', 'La targa può contenere solo lettere, cifre, spazi e trattini.'],
    'passeggeri' => [['travel' => ['passengers' => 12]], 'travel.passengers', 'I passeggeri devono essere da 1 a 9.'],
    'fattura senza paese' => [['billing' => ['name' => 'Rossi srl']], 'billing.country', 'Il campo è obbligatorio.'],
]);

it('crea la prenotazione una volta sola con la stessa Idempotency-Key', function () {
    $body = EngineApi::reservationBody(EngineApi::createQuote($this, $this->headers));
    $headers = $this->headers + ['Idempotency-Key' => 'chiave-di-prova-1'];

    $first = $this->postJson('/api/v1/reservations', $body, $headers)->assertCreated();
    $replay = $this->postJson('/api/v1/reservations', $body, $headers);

    $this->assertMatchesOpenApi($replay, '/reservations', 'post');
    $replay->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertHeader('Location', $first->headers->get('Location'));
    expect($replay->getContent())->toBe($first->getContent())
        ->and(Reservation::query()->count())->toBe(1);

    $other = $this->postJson('/api/v1/reservations', EngineApi::reservationBody(EngineApi::createQuote($this, $this->headers, ['extras' => []])), $headers);
    $this->assertMatchesOpenApi($other, '/reservations', 'post');
    $other->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
});

it('ripete anche gli errori salvati e rifiuta chiavi troppo corte', function () {
    $body = EngineApi::reservationBody(EngineApi::createQuote($this, $this->headers), ['consents' => ['privacy' => false]]);
    $headers = $this->headers + ['Idempotency-Key' => 'chiave-errore'];

    $this->postJson('/api/v1/reservations', $body, $headers)->assertUnprocessable();
    $this->postJson('/api/v1/reservations', $body, $headers)
        ->assertUnprocessable()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->postJson('/api/v1/reservations', $body, $this->headers + ['Idempotency-Key' => 'corta'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.Idempotency-Key.0', 'La chiave di idempotenza deve avere da 8 a 255 caratteri.');
});

it('crea la prenotazione anche se le email non partono', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP non raggiungibile'));

    $this->postJson('/api/v1/reservations', EngineApi::reservationBody(EngineApi::createQuote($this, $this->headers)), $this->headers)
        ->assertCreated()
        ->assertJsonPath('status', 'confirmed')
        ->assertJsonPath('notifications.email_sent', false);

    expect(Reservation::query()->sole()->email_sent)->toBeFalse();
});

it('fa leggere la prenotazione solo con la chiave segreta del canale', function () {
    $id = $this->postJson('/api/v1/reservations', EngineApi::reservationBody(EngineApi::createQuote($this, $this->headers)), $this->headers)->json('id');

    $own = $this->getJson("/api/v1/reservations/{$id}", $this->headers);
    $this->assertMatchesOpenApi($own, '/reservations/{reservationId}', 'get');
    $own->assertOk()->assertJsonPath('id', $id)->assertJsonPath('customer.first_name', 'Maria');

    $public = $this->getJson("/api/v1/reservations/{$id}", [
        'Authorization' => 'Bearer '.$this->client->publishable_key,
        'Origin' => EngineApi::ORIGIN,
    ]);
    $this->assertMatchesOpenApi($public, '/reservations/{reservationId}', 'get');
    $public->assertForbidden()->assertJsonPath('code', 'forbidden');

    $this->getJson("/api/v1/reservations/{$id}", EngineApi::client('pwa')['headers'])->assertNotFound();
});

it('fa scadere le prenotazioni online non pagate entro il termine', function () {
    $now = now();
    $late = Reservation::factory()->status(ReservationStatus::PendingPayment, $now->toImmutable()->subMinute())->create();
    $inTime = Reservation::factory()->status(ReservationStatus::PendingPayment, $now->toImmutable()->addMinutes(10))->create();
    $confirmed = Reservation::factory()->create();

    EngineApi::freezeClock($now->format(DATE_ATOM));
    $this->artisan('engine:expire-pending')->expectsOutputToContain('Prenotazioni scadute: 1.')->assertSuccessful();

    expect($late->fresh()?->status)->toBe(ReservationStatus::Expired)
        ->and($inTime->fresh()?->status)->toBe(ReservationStatus::PendingPayment)
        ->and($confirmed->fresh()?->status)->toBe(ReservationStatus::Confirmed);
});
