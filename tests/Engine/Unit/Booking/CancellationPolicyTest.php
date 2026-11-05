<?php

use App\Engine\Domain\Booking\CancellationDecision;
use App\Engine\Domain\Booking\CancellationPolicy;
use App\Engine\Domain\Booking\CancellationReason;
use App\Engine\Domain\Booking\ReservationStatus as S;

function decideAt(string $now, S $status = S::Confirmed, bool $paidOnline = false): CancellationDecision
{
    $rome = new DateTimeZone('Europe/Rome');

    return (new CancellationPolicy(24))->decide($status, new DateTimeImmutable('2026-12-20 08:00', $rome), $paidOnline, new DateTimeImmutable($now, $rome));
}

it('ammette la cancellazione fino a 24 ore prima della consegna, estremo incluso', function (string $now, bool $cancellable) {
    $decision = decideAt($now);

    expect($decision->cancellable)->toBe($cancellable)
        ->and($decision->reason)->toBe($cancellable ? null : CancellationReason::WindowClosed)
        ->and($decision->deadline->format('Y-m-d H:i'))->toBe('2026-12-19 08:00');
})->with([
    '24h01 prima' => ['2026-12-19 07:59', true],
    'esattamente 24h prima' => ['2026-12-19 08:00', true],
    '23h59 prima' => ['2026-12-19 08:01', false],
    'a sosta iniziata' => ['2026-12-20 09:00', false],
]);

it('dipende dallo stato della prenotazione', function (S $status, ?CancellationReason $reason) {
    expect(decideAt('2026-12-01 10:00', $status)->reason)->toBe($reason);
})->with([
    [S::PendingPayment, null],
    [S::Confirmed, null],
    [S::Paid, null],
    [S::Cancelled, CancellationReason::AlreadyCancelled],
    [S::Expired, CancellationReason::NotCancellableStatus],
    [S::Completed, CancellationReason::NotCancellableStatus],
]);

it('chiede il rimborso solo per le prenotazioni pagate online', function () {
    expect(decideAt('2026-12-01 10:00', S::Paid, paidOnline: true)->refundRequired)->toBeTrue()
        ->and(decideAt('2026-12-01 10:00')->refundRequired)->toBeFalse();
});
