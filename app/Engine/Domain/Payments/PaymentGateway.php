<?php

namespace App\Engine\Domain\Payments;

use App\Engine\Domain\Shared\Money;

/**
 * Provider di pagamento online (PayPal Orders v2). L'importo lo decide sempre il server.
 *
 * Gli errori di rete o del provider (5xx, timeout, credenziali) sono PaymentProviderError;
 * un pagamento non andato a buon fine è invece un GatewayCapture con stato diverso da COMPLETED.
 */
interface PaymentGateway
{
    public function provider(): string;

    /**
     * @param  string  $reference  id della prenotazione, riportato nell'ordine
     */
    public function createOrder(string $reference, Money $amount, string $description): GatewayOrder;

    /**
     * Incassa un ordine approvato dal cliente. Idempotente: un ordine già incassato restituisce lo stesso esito.
     */
    public function captureOrder(string $orderId): GatewayCapture;

    /**
     * Rimborsa (anche in parte) un incasso. Idempotente per incasso e importo.
     */
    public function refundCapture(string $captureId, Money $amount, string $note): GatewayRefund;

    /**
     * Verifica la firma di una notifica (webhook) del provider.
     *
     * @param  array<string, string>  $headers  nomi in minuscolo
     */
    public function verifyWebhook(array $headers, string $body): bool;
}
