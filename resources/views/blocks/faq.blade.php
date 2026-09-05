@php $items = $data['items'] ?? []; @endphp
@if(! empty($items))
    <section class="mx-auto max-w-4xl px-6 py-16">
        @if(! empty($data['heading']))
            <h2 class="text-2xl font-bold text-slate-900">{{ $data['heading'] }}</h2>
        @endif
        <div class="mt-6 divide-y divide-slate-200">
            @foreach($items as $item)
                <details class="group py-4">
                    <summary class="cursor-pointer font-semibold text-slate-900 marker:content-none">
                        {{ $item['question'] ?? '' }}
                    </summary>
                    <p class="mt-2 text-slate-600">{{ $item['answer'] ?? '' }}</p>
                </details>
            @endforeach
        </div>
    </section>
@endif
