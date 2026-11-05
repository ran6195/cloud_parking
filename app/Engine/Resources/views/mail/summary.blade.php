@php($rome = 'Europe/Rome')
- **Numero:** {{ $reservation->number }}
- **Consegna:** {{ $reservation->starts_at->setTimezone($rome)->format('d/m/Y H:i') }}
- **Ritiro:** {{ $reservation->ends_at->setTimezone($rome)->format('d/m/Y H:i') }}
- **Targa:** {{ $reservation->plate }}

<x-mail::table>
| Voce | Q.tà | Importo |
|:-----|:----:|-------:|
@foreach ($reservation->lines as $line)
| {{ $line->description }} | {{ $line->quantity }} | {{ number_format($line->total_cents / 100, 2, ',', '.') }} € |
@endforeach
@if ($reservation->discount_cents > 0)
| Sconto | | −{{ number_format($reservation->discount_cents / 100, 2, ',', '.') }} € |
@endif
| **Totale** | | **{{ number_format($reservation->total_cents / 100, 2, ',', '.') }} €** |
</x-mail::table>
