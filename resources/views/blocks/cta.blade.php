<section class="bg-slate-900 py-16">
    <div class="mx-auto max-w-4xl px-6 text-center">
        @if(! empty($data['heading']))
            <h2 class="text-3xl font-bold text-white">{{ $data['heading'] }}</h2>
        @endif
        @if(! empty($data['text']))
            <p class="mt-4 text-slate-300">{{ $data['text'] }}</p>
        @endif
        @if(! empty($data['button_label']) && ! empty($data['button_url']))
            <a href="{{ $data['button_url'] }}" class="mt-8 inline-block rounded-md bg-white px-6 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-100">
                {{ $data['button_label'] }}
            </a>
        @endif
    </div>
</section>
