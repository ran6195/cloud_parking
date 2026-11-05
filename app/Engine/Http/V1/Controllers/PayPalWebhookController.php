<?php

namespace App\Engine\Http\V1\Controllers;

use App\Engine\Application\PaymentService;
use App\Engine\Domain\Payments\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Notifiche di PayPal. Autenticate dalla firma, non dalle chiavi dei client.
 * Rispondono 204 anche per eventi ignorati, così PayPal non li ripete.
 */
final class PayPalWebhookController
{
    public function __invoke(Request $request, PaymentGateway $gateway, PaymentService $payments): Response
    {
        $body = $request->getContent();
        $headers = array_map(
            fn (array $values) => (string) ($values[0] ?? ''),
            array_change_key_case($request->headers->all(), CASE_LOWER),
        );

        if (! $gateway->verifyWebhook($headers, $body)) {
            return response()->noContent(400);
        }

        $event = json_decode($body, true);
        if (is_array($event)) {
            $payments->handleWebhook($event);
        }

        return response()->noContent();
    }
}
