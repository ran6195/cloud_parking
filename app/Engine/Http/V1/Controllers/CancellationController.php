<?php

namespace App\Engine\Http\V1\Controllers;

use App\Engine\Application\CancellationService;
use App\Engine\Application\QuoteSnapshot;
use App\Engine\Http\V1\Presenters\MoneyJson;
use App\Engine\Http\V1\Requests\CancelReservationRequest;
use Illuminate\Http\JsonResponse;
use LogicException;

/**
 * Cancellazione da parte del cliente con il token dell'email. Il token è il segreto:
 * vale da qualsiasi interfaccia, non solo da quella che ha creato la prenotazione.
 */
final class CancellationController
{
    public function show(CancellationService $cancellations, string $token): JsonResponse
    {
        $reservation = $cancellations->find($token);
        $decision = $cancellations->decide($reservation);

        return response()->json([
            'reservation' => [
                'number' => $reservation->number,
                'status' => $reservation->status->value,
                'start' => QuoteSnapshot::dateTime($reservation->starts_at->toDateTimeImmutable()),
                'end' => QuoteSnapshot::dateTime($reservation->ends_at->toDateTimeImmutable()),
                'plate' => $reservation->plate,
            ],
            'cancellable' => $decision->cancellable,
            'reason' => $decision->reason?->value,
            'deadline' => QuoteSnapshot::dateTime($decision->deadline),
            'refund_required' => $decision->refundRequired,
        ]);
    }

    public function store(CancelReservationRequest $request, CancellationService $cancellations, string $token): JsonResponse
    {
        $cancellations->find($token);
        ['reservation' => $reservation, 'email_sent' => $emailSent] = $cancellations->cancel(
            $token,
            $request->validated('reason'),
            $request->refund(),
        );

        $refund = $reservation->refund;

        return response()->json([
            'number' => $reservation->number,
            'status' => $reservation->status->value,
            'cancelled_at' => QuoteSnapshot::dateTime(
                ($reservation->cancelled_at ?? throw new LogicException('Prenotazione annullata senza data.'))->toDateTimeImmutable(),
            ),
            'notifications' => ['email_sent' => $emailSent],
            'refund' => $refund === null ? null : [
                'status' => $refund['status'],
                'amount' => MoneyJson::cents((int) $refund['amount_cents']),
            ],
        ]);
    }
}
