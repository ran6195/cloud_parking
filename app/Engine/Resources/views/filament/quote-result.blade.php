@php
    $euro = fn (?int $cents) => $cents === null ? '—' : number_format($cents / 100, 2, ',', '.').' €';
    $reasons = ['blocked_date' => 'giorno bloccato', 'capacity_full' => 'posti esauriti', 'service_disabled' => 'servizio non attivo'];
    $discountReasons = ['not_found' => 'inesistente', 'expired' => 'scaduto', 'exhausted' => 'esaurito'];
@endphp
<div class="fi-prose" style="display: grid; gap: 1rem">
    @if ($error)
        <p style="color: var(--danger-600)"><strong>Non calcolabile:</strong> {{ $error }}</p>
    @elseif ($result)
        <p>
            <strong>{{ $result['stay']['billable_days'] }} giorni</strong> fatturati
            ({{ intdiv($result['stay']['duration_minutes'], 60) }} h {{ $result['stay']['duration_minutes'] % 60 }} min
            @if ($result['stay']['days_after_30'] > 0), di cui {{ $result['stay']['days_after_30'] }} oltre il 30°@endif).
            Listino <strong>{{ $result['trace']['price_table'] }}</strong>
            @if ($result['trace']['fallback_years'] > 0)
                <span style="color: var(--warning-600)">· listino mancante: usato quello di {{ $result['trace']['fallback_years'] }} anno/i prima ({{ \Carbon\Carbon::parse($result['trace']['matched_date'])->format('d/m/Y') }})</span>
            @endif
        </p>

        <table style="width: 100%">
            <thead><tr><th style="text-align: left">Voce</th><th>Q.tà</th><th style="text-align: right">Importo</th></tr></thead>
            <tbody>
            @foreach ($result['lines'] as $line)
                <tr><td>{{ $line['description'] }}</td><td style="text-align: center">{{ $line['quantity'] }}</td><td style="text-align: right">{{ $euro($line['total_cents']) }}</td></tr>
            @endforeach
            <tr><td colspan="2">Subtotale</td><td style="text-align: right">{{ $euro($result['subtotal_cents']) }}</td></tr>
            @if ($result['discount'])
                <tr>
                    <td colspan="2">Sconto {{ $result['discount']['code'] }}
                        @unless ($result['discount']['applied']) <em>(non applicato: {{ $discountReasons[$result['discount']['reason']] ?? $result['discount']['reason'] }})</em> @endunless
                    </td>
                    <td style="text-align: right">{{ $result['discount']['applied'] ? '−'.$euro($result['discount']['amount_cents']) : '—' }}</td>
                </tr>
            @endif
            <tr><td colspan="2"><strong>Totale</strong></td><td style="text-align: right"><strong>{{ $euro($result['total_cents']) }}</strong></td></tr>
            </tbody>
        </table>

        <p>
            <strong>Disponibilità:</strong>
            @if ($result['availability']['available'])
                prenotabile.
            @else
                non prenotabile.
                @if (! $result['availability']['parking']['available'])
                    Parcheggio: {{ $reasons[$result['availability']['parking']['reason']] ?? '' }} ({{ implode(', ', array_map(fn ($d) => \Carbon\Carbon::parse($d)->format('d/m'), $result['availability']['parking']['unavailable_dates'])) }}).
                @endif
                @foreach ($result['availability']['extras'] as $id => $item)
                    @unless ($item['available'])
                        {{ \App\Engine\Filament\Support\Labels::extras()[$id] ?? $id }}: {{ $reasons[$item['reason']] ?? '' }}@if ($item['unavailable_dates']) ({{ implode(', ', array_map(fn ($d) => \Carbon\Carbon::parse($d)->format('d/m'), $item['unavailable_dates'])) }})@endif.
                    @endunless
                @endforeach
            @endif
        </p>
    @endif
</div>
