<x-mail::message>
# Nuova richiesta dal sito

**Nome:** {{ $lead->name }}
**Email:** {{ $lead->email }}
@if($lead->phone)
**Telefono:** {{ $lead->phone }}
@endif
@if($lead->source_url)
**Pagina:** {{ $lead->source_url }}
@endif

@if($lead->message)
**Messaggio:**
{{ $lead->message }}
@endif

<x-mail::button :url="url('/admin/leads')">
Vai al pannello
</x-mail::button>
</x-mail::message>
