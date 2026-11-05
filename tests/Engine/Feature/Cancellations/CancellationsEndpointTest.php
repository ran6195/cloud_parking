<?php

use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Payments\PaymentGateway;
use App\Engine\Mail\ReservationCancelled;
use App\Engine\Mail\ReservationCancelledNotice;
use App\Engine\Models\PaymentTransaction;
use App\Engine\Models\Reservation;
use App\Engine\Settings\EngineSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RefreshEngineDatabase;
use Tests\Concerns\ValidatesOpenApi;
use Tests\Engine\Support\EngineApi;

uses(RefreshEngineDatabase::class, ValidatesOpenApi::class);

const VALID_TAX_CODE = 'RSSMRA80A41A944I';

beforeEach(function () {
    Mail::fake();
    EngineApi::freezeClock();
    ['headers' => $this->headers] = EngineApi::client();
    app(EngineSettings::class)->set('contact.email', 'gestore@example.com');
});

/** Prenotazione dal 20/11/2026 08:00, da pagare in parcheggio. */
function confirmedReservation(): Reservation
{
    return Reservation::factory()->between('2026-11-20 08:00', '2026-11-23 08:00')->create()->refresh();
}

/** Prenotazione pagata online da 37,80 €, con l'incasso PayPal registrato. */
function paidOnlineReservation(): Reservation
{
    $reservation = Reservation::factory()->between('2026-11-20 08:00', '2026-11-23 08:00')->status(ReservationStatus::Paid)->create([
        'payment_method' => 'online', 'paid_at' => CarbonImmutable::parse('2026-11-05 11:00', 'Europe/Rome'),
        'subtotal_cents' => 3780, 'total_cents' => 3780,
    ]);
    PaymentTransaction::factory()->create(['reservation_id' => $reservation->id, 'order_id' => 'ORD-1', 'capture_id' => 'CAP-1', 'amount_cents' => 3780]);

    return $reservation->refresh();
}

function cancellationUrl(Reservation $reservation): string
{
    return '/api/v1/cancellations/'.$reservation->cancellation_token;
}

it('mostra se la prenotazione è annullabile e fino a quando', function () {
    $reservation = confirmedReservation();

    $response = $this->getJson(cancellationUrl($reservation), $this->headers);

    $this->assertMatchesOpenApi($response, '/cancellations/{token}', 'get');
    $response->assertOk()->assertExactJson([
        'reservation' => [
            'number' => $reservation->number, 'status' => 'confirmed',
            'start' => '2026-11-20T08:00:00+01:00', 'end' => '2026-11-23T08:00:00+01:00', 'plate' => $reservation->plate,
        ],
        'cancellable' => true,
        'reason' => null,
        'deadline' => '2026-11-19T08:00:00+01:00',
        'refund_required' => false,
    ]);
});

it('annulla, libera il posto e avvisa cliente e gestore', function () {
    $reservation = confirmedReservation();

    $response = $this->postJson(cancellationUrl($reservation), ['reason' => 'Volo cancellato'], $this->headers);

    $this->assertMatchesOpenApi($response, '/cancellations/{token}', 'post');
    $response->assertOk()->assertExactJson([
        'number' => $reservation->number,
        'status' => 'cancelled',
        'cancelled_at' => '2026-11-05T12:00:00+01:00',
        'notifications' => ['email_sent' => true],
        'refund' => null,
    ]);

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Cancelled)
        ->and($reservation->cancellation_reason)->toBe('Volo cancellato')
        ->and($reservation->status->holdsCapacity($reservation->payment_due_by?->toDateTimeImmutable(), new DateTimeImmutable))->toBeFalse();
    Mail::assertQueued(ReservationCancelled::class, fn ($mail) => $mail->hasTo($reservation->customer?->email));
    Mail::assertQueued(ReservationCancelledNotice::class, fn ($mail) => $mail->hasTo('gestore@example.com'));
});

it('rifiuta la cancellazione fuori tempo, già fatta o di una prenotazione scaduta', function (Closure $prepare, string $code, string $detail) {
    $reservation = $prepare();

    $preview = $this->getJson(cancellationUrl($reservation), $this->headers);
    $this->assertMatchesOpenApi($preview, '/cancellations/{token}', 'get');
    $preview->assertJsonPath('cancellable', false)->assertJsonPath('reason', $code);

    $response = $this->postJson(cancellationUrl($reservation), [], $this->headers);
    $this->assertMatchesOpenApi($response, '/cancellations/{token}', 'post');
    $response->assertConflict()->assertJsonPath('code', $code)->assertJsonPath('detail', $detail);
    Mail::assertNothingQueued();
})->with([
    'meno di 24 ore prima' => [function () {
        $reservation = confirmedReservation();
        EngineApi::freezeClock('2026-11-19 08:01:00 Europe/Rome');

        return $reservation;
    }, 'cancellation_window_closed', 'La cancellazione gratuita era possibile fino al 19/11/2026 alle 08:00.'],
    'già annullata' => [fn () => Reservation::factory()->status(ReservationStatus::Cancelled)->create(), 'already_cancelled', 'La prenotazione è già stata annullata.'],
    'scaduta' => [fn () => Reservation::factory()->status(ReservationStatus::Expired)->create(['payment_method' => 'online']), 'not_cancellable_status', 'La prenotazione non si può più annullare: è scaduta o conclusa.'],
]);

it('per una prenotazione pagata online chiede i dati del rimborso', function (array $body, string $message) {
    $reservation = paidOnlineReservation();
    $this->getJson(cancellationUrl($reservation), $this->headers)->assertJsonPath('refund_required', true);

    $response = $this->postJson(cancellationUrl($reservation), $body, $this->headers);

    $this->assertMatchesOpenApi($response, '/cancellations/{token}', 'post');
    $response->assertUnprocessable()->assertJsonPath('errors', ['refund.tax_code' => [$message]]);
    expect($reservation->refresh()->status)->toBe(ReservationStatus::Paid);
})->with([
    'senza rimborso' => [[], 'Per il rimborso serve il codice fiscale di chi ha pagato.'],
    'codice non valido' => [['refund' => ['tax_code' => 'ABC']], 'Il codice fiscale o la partita IVA non sono validi.'],
]);

it('registra la richiesta di rimborso manuale e la segnala al gestore', function () {
    $reservation = paidOnlineReservation();

    $response = $this->postJson(cancellationUrl($reservation), ['refund' => ['tax_code' => ' rssmra80a41a944i ', 'notes' => 'Conto PayPal di mio marito']], $this->headers);

    $this->assertMatchesOpenApi($response, '/cancellations/{token}', 'post');
    $response->assertOk()->assertJsonPath('refund', ['status' => 'requested', 'amount' => ['amount_cents' => 3780, 'currency' => 'EUR']]);

    expect($reservation->refresh()->refund)->toMatchArray([
        'status' => 'requested', 'amount_cents' => 3780, 'tax_code' => VALID_TAX_CODE, 'notes' => 'Conto PayPal di mio marito',
    ])->and(app(PaymentGateway::class)->refundedCents('CAP-1'))->toBe(0);
    Mail::assertQueued(ReservationCancelledNotice::class, fn (ReservationCancelledNotice $mail) => str_contains($mail->envelope()->subject ?? '', 'RIMBORSO DA FARE')
        && str_contains($mail->render(), VALID_TAX_CODE));
});

it('con i rimborsi automatici rimborsa subito su PayPal', function () {
    app(EngineSettings::class)->set('refunds.mode', 'automatic');
    $reservation = paidOnlineReservation();

    $this->postJson(cancellationUrl($reservation), ['refund' => ['tax_code' => VALID_TAX_CODE]], $this->headers)
        ->assertOk()
        ->assertJsonPath('refund.status', 'refunded');

    expect(app(PaymentGateway::class)->refundedCents('CAP-1'))->toBe(3780)
        ->and($reservation->refresh()->refund['mode'])->toBe('automatic')
        ->and($reservation->refund['provider_refund_id'])->toStartWith('FAKEREF')
        ->and(PaymentTransaction::query()->sole()->status)->toBe('REFUNDED');

    $this->getJson("/api/v1/reservations/{$reservation->id}", $this->headers)->assertJsonPath('payment.status', 'refunded');
});

it('se il rimborso automatico fallisce annulla comunque e lascia il rimborso al gestore', function () {
    Log::spy();
    app(EngineSettings::class)->set('refunds.mode', 'automatic');
    app(PaymentGateway::class)->declineRefunds();
    $reservation = paidOnlineReservation();

    $this->postJson(cancellationUrl($reservation), ['refund' => ['tax_code' => VALID_TAX_CODE]], $this->headers)
        ->assertOk()
        ->assertJsonPath('status', 'cancelled')
        ->assertJsonPath('refund.status', 'failed');

    expect($reservation->refresh()->refund['error'])->toBe('PayPal ha rifiutato il rimborso (FAILED).');
    Log::shouldHaveReceived('error')->once();
});

it('risponde 404 a token inesistenti o malformati', function (string $token) {
    $response = $this->getJson("/api/v1/cancellations/{$token}", $this->headers);

    $response->assertNotFound()->assertJsonPath('code', 'not_found');
})->with([str_repeat('a', 64), 'non-un-token']);

it('gestisce i rimborsi da riga di comando', function () {
    $manual = paidOnlineReservation();
    $this->postJson(cancellationUrl($manual), ['refund' => ['tax_code' => VALID_TAX_CODE]], $this->headers)->assertOk();

    $this->artisan('engine:refunds')->expectsOutputToContain((string) $manual->number)->assertSuccessful();
    $this->artisan('engine:refunds', ['number' => $manual->number])->expectsOutputToContain('--automatic oppure --mark-refunded')->assertFailed();

    $this->artisan('engine:refunds', ['number' => $manual->number, '--automatic' => true])->assertSuccessful();
    expect($manual->refresh()->refund['status'])->toBe('refunded')
        ->and(app(PaymentGateway::class)->refundedCents('CAP-1'))->toBe(3780);

    $this->artisan('engine:refunds', ['number' => $manual->number, '--mark-refunded' => true])
        ->expectsOutputToContain('non ha un rimborso da fare')->assertFailed();
    $this->artisan('engine:refunds')->expectsOutputToContain('Nessun rimborso da fare.')->assertSuccessful();
});

it('segna come rimborsata una prenotazione rimborsata a mano', function () {
    $reservation = paidOnlineReservation();
    $this->postJson(cancellationUrl($reservation), ['refund' => ['tax_code' => VALID_TAX_CODE]], $this->headers)->assertOk();

    $this->artisan('engine:refunds', ['number' => $reservation->number, '--mark-refunded' => true, '--note' => 'Rimborsato dal pannello PayPal'])
        ->assertSuccessful();

    expect($reservation->refresh()->refund)->toMatchArray(['status' => 'refunded', 'mode' => 'manual', 'manager_note' => 'Rimborsato dal pannello PayPal']);
});
