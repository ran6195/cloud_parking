<?php

namespace App\Engine\Http\V1\Controllers;

use App\Engine\Application\PaymentService;
use App\Engine\Http\V1\Middleware\AuthenticateClient;
use App\Engine\Http\V1\Presenters\MoneyJson;
use App\Engine\Http\V1\Presenters\ReservationPresenter;
use App\Engine\Http\V1\Requests\CapturePaymentRequest;
use App\Engine\Models\Reservation;
use App\Engine\Settings\EngineSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Pagamento PayPal di una prenotazione del canale del client (chiave pk_ o sk_).
 */
final class PaymentController
{
    public function createOrder(Request $request, PaymentService $payments, string $reservationId): JsonResponse
    {
        $transaction = $payments->createOrder($this->reservation($request, $reservationId));

        return response()->json([
            'order_id' => $transaction->order_id,
            'amount' => MoneyJson::cents($transaction->amount_cents),
            'status' => $transaction->status === 'APPROVED' ? 'APPROVED' : 'CREATED',
        ], 201);
    }

    public function capture(CapturePaymentRequest $request, PaymentService $payments, EngineSettings $settings, string $reservationId): JsonResponse
    {
        $reservation = $payments->capture(
            $this->reservation($request, $reservationId),
            $request->string('order_id')->value(),
        );

        return response()->json(ReservationPresenter::present($reservation, $settings->cancellationHours()));
    }

    private function reservation(Request $request, string $reservationId): Reservation
    {
        return Reservation::query()
            ->whereKey($reservationId)
            ->where('channel', AuthenticateClient::client($request)->channel)
            ->first() ?? throw new NotFoundHttpException;
    }
}
