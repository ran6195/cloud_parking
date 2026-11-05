<?php

use App\Engine\Domain\Booking\ReservationStatus as S;

it('consente solo le transizioni previste', function (S $from, array $allowed) {
    foreach (S::cases() as $to) {
        expect($from->canTransitionTo($to))->toBe(in_array($to, $allowed, true), "{$from->value} → {$to->value}");
    }
})->with([
    'in attesa di pagamento' => [S::PendingPayment, [S::Paid, S::Expired, S::Cancelled]],
    'confermata' => [S::Confirmed, [S::Completed, S::Cancelled]],
    'pagata' => [S::Paid, [S::Completed, S::Cancelled]],
    'conclusa' => [S::Completed, []],
    'annullata' => [S::Cancelled, []],
    'scaduta' => [S::Expired, []],
]);
