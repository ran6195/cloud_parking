<x-filament-widgets::widget>
    <x-filament::section heading="Occupazione dei prossimi 14 giorni">
        @php($pct = fn (int $used, int $cap) => $cap > 0 ? min(100, (int) round($used * 100 / $cap)) : 0)
        <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem">
            <thead>
                <tr style="text-align: left">
                    <th style="padding: 0.25rem 0.5rem">Giorno</th>
                    <th style="padding: 0.25rem 0.5rem">Parcheggio</th>
                    <th style="padding: 0.25rem 0.5rem">Posto coperto</th>
                    <th style="padding: 0.25rem 0.5rem">Tieni le chiavi</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($rows as $row)
                <tr style="border-top: 1px solid rgb(0 0 0 / 0.06)">
                    <td style="padding: 0.25rem 0.5rem; white-space: nowrap">
                        {{ ucfirst(\Carbon\Carbon::instance($row['date'])->locale('it')->isoFormat('ddd D/MM')) }}
                        @if ($row['blocked']) <x-filament::badge color="danger" size="sm">bloccato</x-filament::badge> @endif
                    </td>
                    <td style="padding: 0.25rem 0.5rem">
                        {{ $row['parking'] }} / {{ $totalSpots }}
                        <div style="height: 4px; background: rgb(0 0 0 / 0.08); border-radius: 2px">
                            <div style="height: 4px; width: {{ $pct($row['parking'], $totalSpots) }}%; border-radius: 2px; background: {{ $pct($row['parking'], $totalSpots) >= 90 ? 'rgb(220 38 38)' : 'rgb(2 132 199)' }}"></div>
                        </div>
                    </td>
                    <td style="padding: 0.25rem 0.5rem">{{ $row['covered'] }} / {{ $coveredCapacity }}</td>
                    <td style="padding: 0.25rem 0.5rem">{{ $row['keys'] }} / {{ $keysCapacity }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
