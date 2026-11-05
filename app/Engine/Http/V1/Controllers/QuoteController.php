<?php

namespace App\Engine\Http\V1\Controllers;

use App\Engine\Application\QuoteService;
use App\Engine\Domain\Shared\Clock;
use App\Engine\Http\V1\Middleware\AuthenticateClient;
use App\Engine\Http\V1\Presenters\QuotePresenter;
use App\Engine\Http\V1\Requests\CreateQuoteRequest;
use App\Engine\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Le dipendenze si risolvono nei metodi: Laravel riusa l'istanza del controller tra le richieste.
 */
final class QuoteController
{
    public function store(CreateQuoteRequest $request, QuoteService $quotes, Clock $clock): JsonResponse
    {
        $quote = $quotes->create($request->toQuoteRequest(), AuthenticateClient::client($request));

        return response()
            ->json(QuotePresenter::present($quote, CarbonImmutable::instance($clock->now())), 201)
            ->header('Location', '/api/v1/quotes/'.$quote->id);
    }

    /**
     * Un client vede solo i propri preventivi.
     */
    public function show(Request $request, Clock $clock, string $quoteId): JsonResponse
    {
        $quote = Quote::query()
            ->whereKey($quoteId)
            ->where('api_client_id', AuthenticateClient::client($request)->id)
            ->first() ?? throw new NotFoundHttpException;

        return response()->json(QuotePresenter::present($quote, CarbonImmutable::instance($clock->now())));
    }
}
