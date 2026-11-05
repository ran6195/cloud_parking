<x-mail::message>
# Nuova prenotazione

{{ $reservation->customer->first_name }} {{ $reservation->customer->last_name }}
· {{ $reservation->customer->email }} · {{ $reservation->customer->phone }}

@include('engine::mail.summary')

- **Canale:** {{ $reservation->channel }}
- **Stato:** {{ $reservation->status->value }}
- **Pagamento:** {{ $reservation->payment_method->value }}
@if ($reservation->outbound_flight || $reservation->return_flight)
- **Voli:** {{ $reservation->outbound_flight ?? '—' }} / {{ $reservation->return_flight ?? '—' }}
@endif
- **Passeggeri:** {{ $reservation->passengers }}
@if ($reservation->notes)

**Note:** {{ $reservation->notes }}
@endif
</x-mail::message>
