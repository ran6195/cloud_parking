@php($rome = 'Europe/Rome')
<!doctype html>
<html lang="it">
<head><meta charset="utf-8"><title>Ricevuta n. {{ $reservation->number }}</title></head>
<body>
<h1>Ricevuta provvisoria n. {{ $reservation->number }}</h1>
<p>{{ $reservation->customer->first_name }} {{ $reservation->customer->last_name }} · targa {{ $reservation->plate }}</p>
<p>Dal {{ $reservation->starts_at->setTimezone($rome)->format('d/m/Y H:i') }} al {{ $reservation->ends_at->setTimezone($rome)->format('d/m/Y H:i') }}</p>
<table>
@foreach ($reservation->lines as $line)
    <tr><td>{{ $line->description }}</td><td>{{ $line->quantity }}</td><td>{{ number_format($line->total_cents / 100, 2, ',', '.') }} €</td></tr>
@endforeach
@if ($reservation->discount_cents > 0)
    <tr><td>Sconto</td><td></td><td>−{{ number_format($reservation->discount_cents / 100, 2, ',', '.') }} €</td></tr>
@endif
    <tr><th>Totale</th><td></td><th>{{ number_format($reservation->total_cents / 100, 2, ',', '.') }} €</th></tr>
</table>
</body>
</html>
