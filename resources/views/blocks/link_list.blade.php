@php $items = $data['items'] ?? []; @endphp
@if(! empty($items))
    <section class="mx-auto max-w-6xl px-6 py-16">
        @if(! empty($data['heading']))
            <h2 class="text-2xl font-bold text-slate-900">{{ $data['heading'] }}</h2>
        @endif
        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($items as $item)
                <a href="{{ $item['url'] ?? '#' }}" class="rounded-md border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 hover:border-slate-400 hover:text-slate-900">
                    {{ $item['label'] ?? '' }}
                </a>
            @endforeach
        </div>
    </section>
@endif
