@php $items = $data['items'] ?? []; @endphp
@if(! empty($items))
    <x-section :data="$data" default-background="terra">
        @if(! empty($data['heading']))
            <h2 class="titolo-sezione mb-8">{{ $data['heading'] }}</h2>
        @endif
        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($items as $item)
                <li>
                    <a href="{{ $item['url'] ?? '#' }}" class="flex min-h-11 items-center justify-between gap-3 rounded-controllo border border-bordo bg-carta px-5 py-4 font-bold hover:border-fondo">
                        {{ $item['label'] ?? '' }}
                        <x-icona name="arrow-right" class="size-5" />
                    </a>
                </li>
            @endforeach
        </ul>
    </x-section>
@endif
