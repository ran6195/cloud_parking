<x-mail::message>
# Prenotazione annullata dal cliente

{{ $reservation->customer->first_name }} {{ $reservation->customer->last_name }}
· {{ $reservation->customer->email }} · {{ $reservation->customer->phone }}

@include('engine::mail.summary')

@if ($reservation->cancellation_reason)
**Motivo:** {{ $reservation->cancellation_reason }}
@endif

@php($refund = $reservation->refund)
@if ($refund)
## Rimborso: {{ number_format($refund['amount_cents'] / 100, 2, ',', '.') }} €

- **Stato:** {{ ['requested' => 'da fare a mano', 'refunded' => 'eseguito su PayPal', 'failed' => 'automatico non riuscito, da fare a mano'][$refund['status']] ?? $refund['status'] }}
- **Codice fiscale di chi ha pagato:** {{ $refund['tax_code'] }}
@if (! empty($refund['notes']))
- **Note del cliente:** {{ $refund['notes'] }}
@endif
@if (! empty($refund['error']))
- **Errore:** {{ $refund['error'] }}
@endif

@if ($refund['status'] !== 'refunded')
Dopo il rimborso: `php artisan engine:refunds {{ $reservation->number }} --mark-refunded`
@endif
@endif
</x-mail::message>
