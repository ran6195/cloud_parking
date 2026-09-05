@php $items = $data['items'] ?? []; @endphp
<section class="mx-auto max-w-6xl px-6 py-16">
    @if(! empty($data['heading']))
        <h2 class="text-2xl font-bold text-slate-900">{{ $data['heading'] }}</h2>
    @endif
    <div class="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($items as $item)
            @php
                $icon = null;
                if (! empty($item['icon'])) {
                    try {
                        $icon = svg($item['icon'], 'h-8 w-8 text-slate-900')->toHtml();
                    } catch (\Throwable) {
                        $icon = null;
                    }
                }
            @endphp
            <div>
                {!! $icon !!}
                <h3 class="mt-3 font-semibold text-slate-900">{{ $item['title'] ?? '' }}</h3>
                @if(! empty($item['description']))
                    <p class="mt-2 text-sm text-slate-600">{{ $item['description'] }}</p>
                @endif
            </div>
        @endforeach
    </div>
</section>
