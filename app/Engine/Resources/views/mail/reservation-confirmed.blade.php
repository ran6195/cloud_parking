<x-mail::message>
# Prenotazione confermata

Ciao {{ $reservation->customer->first_name }}, la tua sosta è confermata.

@include('engine::mail.summary')

@if ($reservation->payment_method->value === 'on_site')
Pagherai in parcheggio al momento della consegna dell'auto.
@else
Pagamento online ricevuto.
@endif

@if ($cancellationUrl)
<x-mail::button :url="$cancellationUrl">
Annulla la prenotazione
</x-mail::button>
@endif

Per qualsiasi necessità chiamaci al {{ $phone }}.

{{ $brand }}
</x-mail::message>
