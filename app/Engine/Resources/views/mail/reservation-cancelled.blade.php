<x-mail::message>
# Prenotazione annullata

Ciao {{ $reservation->customer->first_name }}, la prenotazione n. {{ $reservation->number }} è stata annullata e il posto è stato liberato.

@php($refund = $reservation->refund)
@if ($refund)
@if (($refund['status'] ?? null) === 'refunded')
Ti abbiamo rimborsato {{ number_format($refund['amount_cents'] / 100, 2, ',', '.') }} € sul conto PayPal usato per pagare. L'accredito può richiedere qualche giorno.
@else
Riceverai il rimborso di {{ number_format($refund['amount_cents'] / 100, 2, ',', '.') }} € sul conto PayPal usato per pagare nei prossimi giorni.
@endif
@endif

Per qualsiasi necessità chiamaci al {{ $phone }}.

{{ $brand }}
</x-mail::message>
