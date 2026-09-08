@php $imageUrl = \App\Support\Uploads::url($data['image'] ?? null); @endphp
<section class="mx-auto max-w-6xl px-6 py-16">
    <div class="grid items-center gap-10 md:grid-cols-2">
        <div>
            @if(! empty($data['heading']))
                <h1 class="text-4xl font-bold tracking-tight text-slate-900">{{ $data['heading'] }}</h1>
            @endif
            @if(! empty($data['subheading']))
                <p class="mt-4 text-lg text-slate-600">{{ $data['subheading'] }}</p>
            @endif
            @if(! empty($data['cta_label']) && ! empty($data['cta_url']))
                <a href="{{ $data['cta_url'] }}" class="mt-8 inline-block rounded-md bg-slate-900 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-700">
                    {{ $data['cta_label'] }}
                </a>
            @endif
        </div>
        @if($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $data['heading'] ?? '' }}" class="w-full rounded-lg" loading="eager">
        @endif
    </div>
</section>
