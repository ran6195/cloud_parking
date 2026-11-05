<?php

namespace App\Engine\Http\V1\Controllers;

use App\Engine\Application\QuoteSnapshot;
use App\Engine\Domain\Availability\AvailabilityChecker;
use App\Engine\Http\V1\Presenters\QuotePresenter;
use App\Engine\Http\V1\Requests\CheckAvailabilityRequest;
use Illuminate\Http\JsonResponse;

final class AvailabilityController
{
    public function __invoke(CheckAvailabilityRequest $request, AvailabilityChecker $checker): JsonResponse
    {
        $availability = $checker->check($request->stay(), $request->extras());

        return response()->json(QuotePresenter::availability(QuoteSnapshot::availability($availability)));
    }
}
