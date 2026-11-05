<?php

namespace App\Engine\Http\V1\Controllers;

use App\Engine\Application\ReservationService;
use App\Engine\Http\V1\Middleware\AuthenticateClient;
use App\Engine\Http\V1\Presenters\ReservationPresenter;
use App\Engine\Http\V1\Problem;
use App\Engine\Http\V1\Requests\CreateReservationRequest;
use App\Engine\Models\Reservation;
use App\Engine\Settings\EngineSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ReservationController
{
    public function store(CreateReservationRequest $request, ReservationService $reservations, EngineSettings $settings): JsonResponse
    {
        $reservation = $reservations->create($request->toInput(), AuthenticateClient::client($request));

        return response()
            ->json(ReservationPresenter::present($reservation, $settings->cancellationHours()), 201)
            ->header('Location', '/api/v1/reservations/'.$reservation->id);
    }

    /**
     * Solo con chiave sk_ e solo per le prenotazioni del canale del client.
     */
    public function show(Request $request, EngineSettings $settings, string $reservationId): JsonResponse
    {
        if (! AuthenticateClient::usesSecretKey($request)) {
            return Problem::response(403, 'forbidden', 'Per leggere una prenotazione serve la chiave segreta.');
        }

        $reservation = Reservation::query()
            ->whereKey($reservationId)
            ->where('channel', AuthenticateClient::client($request)->channel)
            ->first() ?? throw new NotFoundHttpException;

        return response()->json(ReservationPresenter::present($reservation, $settings->cancellationHours()));
    }
}
