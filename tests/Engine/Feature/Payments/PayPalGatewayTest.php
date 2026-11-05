<?php

use App\Engine\Domain\Payments\GatewayCapture;
use App\Engine\Domain\Payments\PaymentProviderError;
use App\Engine\Domain\Shared\Money;
use App\Engine\Infrastructure\Payments\PayPalGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * Il gateway vero, con le risposte di PayPal simulate (Http::fake): nessuna chiamata esce.
 */

const SANDBOX = 'https://api-m.sandbox.paypal.com';

beforeEach(function () {
    Http::preventStrayRequests();
    $this->gateway = new PayPalGateway('client-id', 'segreto', 'WH-123', SANDBOX);
});

function fakePayPal(array $routes): void
{
    Http::fake([SANDBOX.'/v1/oauth2/token' => Http::response(['access_token' => 'TOKEN', 'expires_in' => 32400])] + $routes);
}

it('crea l\'ordine con importo in euro, riferimento e richiesta idempotente', function () {
    fakePayPal([SANDBOX.'/v2/checkout/orders' => Http::response(['id' => '5O190127TN364715T', 'status' => 'CREATED'], 201)]);

    $order = $this->gateway->createOrder('res-uuid', Money::cents(3880), 'Prenotazione n. 22171');

    expect($order->id)->toBe('5O190127TN364715T')->and($order->status)->toBe('CREATED');
    Http::assertSent(fn (Request $request) => $request->url() === SANDBOX.'/v1/oauth2/token'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id:segreto')));
    Http::assertSent(fn (Request $request) => $request->url() === SANDBOX.'/v2/checkout/orders'
        && $request->hasHeader('Authorization', 'Bearer TOKEN')
        && $request->hasHeader('PayPal-Request-Id', 'order-res-uuid-3880')
        && $request['intent'] === 'CAPTURE'
        && $request['purchase_units'][0]['reference_id'] === 'res-uuid'
        && $request['purchase_units'][0]['amount'] === ['currency_code' => 'EUR', 'value' => '38.80']);
});

it('legge l\'incasso completato e riusa il token', function () {
    fakePayPal([SANDBOX.'/v2/checkout/orders/ORD1/capture' => Http::response([
        'id' => 'ORD1', 'status' => 'COMPLETED',
        'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP1', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'EUR', 'value' => '38.80']]]]]],
    ], 201)]);

    $capture = $this->gateway->captureOrder('ORD1');
    $this->gateway->captureOrder('ORD1');

    expect($capture->captureId)->toBe('CAP1')
        ->and($capture->confirms(Money::cents(3880)))->toBeTrue()
        ->and($capture->confirms(Money::cents(3881)))->toBeFalse();
    Http::assertSentCount(3);
});

it('distingue un ordine non approvato da uno già incassato', function () {
    fakePayPal([
        SANDBOX.'/v2/checkout/orders/NUOVO/capture' => Http::response(['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'ORDER_NOT_APPROVED']]], 422),
        SANDBOX.'/v2/checkout/orders/VECCHIO/capture' => Http::response(['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'ORDER_ALREADY_CAPTURED']]], 422),
        SANDBOX.'/v2/checkout/orders/VECCHIO' => Http::response([
            'id' => 'VECCHIO', 'status' => 'COMPLETED',
            'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP9', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'EUR', 'value' => '10.00']]]]]],
        ]),
    ]);

    expect($this->gateway->captureOrder('NUOVO')->status)->toBe('ORDER_NOT_APPROVED')
        ->and($this->gateway->captureOrder('VECCHIO')->captureId)->toBe('CAP9')
        ->and($this->gateway->captureOrder('VECCHIO')->status)->toBe(GatewayCapture::COMPLETED);
});

it('trasforma errori del server e di rete in PaymentProviderError', function () {
    fakePayPal([
        SANDBOX.'/v2/checkout/orders' => Http::response(['name' => 'INTERNAL_SERVER_ERROR'], 500),
        SANDBOX.'/v2/checkout/orders/RETE/capture' => fn () => throw new ConnectionException('timeout'),
    ]);

    expect(fn () => $this->gateway->createOrder('r', Money::cents(100), 'x'))->toThrow(PaymentProviderError::class, 'HTTP 500')
        ->and(fn () => $this->gateway->captureOrder('RETE'))->toThrow(PaymentProviderError::class, 'connessione');
});

it('verifica la firma dei webhook con l\'API di PayPal', function (string $status, bool $valid) {
    fakePayPal([SANDBOX.'/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => $status])]);

    $headers = ['paypal-auth-algo' => 'SHA256withRSA', 'paypal-cert-url' => 'https://api.paypal.com/cert', 'paypal-transmission-id' => 'T1', 'paypal-transmission-sig' => 'SIG', 'paypal-transmission-time' => '2026-11-05T11:00:00Z'];

    expect($this->gateway->verifyWebhook($headers, '{"id":"WH-EVENT","event_type":"PAYMENT.CAPTURE.COMPLETED"}'))->toBe($valid);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'verify-webhook-signature')
        && $request['webhook_id'] === 'WH-123' && $request['transmission_sig'] === 'SIG' && $request['webhook_event']['id'] === 'WH-EVENT');
})->with([['SUCCESS', true], ['FAILURE', false]]);

it('rifiuta i webhook se manca il Webhook ID', function () {
    $gateway = new PayPalGateway('client-id', 'segreto', null, SANDBOX);

    expect($gateway->verifyWebhook([], '{}'))->toBeFalse();
});
