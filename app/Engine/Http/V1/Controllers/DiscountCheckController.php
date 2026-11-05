<?php

namespace App\Engine\Http\V1\Controllers;

use App\Engine\Domain\Pricing\Discount\DiscountCode;
use App\Engine\Domain\Pricing\Discount\DiscountFinder;
use App\Engine\Domain\Pricing\Discount\DiscountRejection;
use App\Engine\Domain\Shared\Clock;
use App\Engine\Http\V1\Requests\CheckDiscountRequest;
use Illuminate\Http\JsonResponse;

/**
 * Riscontro immediato sul codice sconto nel form. Il preventivo lo riverifica comunque.
 */
final class DiscountCheckController
{
    public function __invoke(CheckDiscountRequest $request, DiscountFinder $finder, Clock $clock): JsonResponse
    {
        $code = new DiscountCode($request->string('code')->value());
        $discount = $finder->find($code);
        $reason = $discount === null ? DiscountRejection::NotFound : $discount->rejectionAt($clock->now());
        $valid = $discount !== null && $reason === null;

        return response()->json([
            'code' => $code->value,
            'valid' => $valid,
            'reason' => $reason?->value,
            'type' => $valid ? $discount->type->value : null,
            'percentage' => $valid ? $discount->percentage : null,
            'amount' => $valid ? $discount->amount?->jsonSerialize() : null,
            'valid_until' => $valid ? $discount->validUntil?->format('Y-m-d') : null,
        ]);
    }
}
