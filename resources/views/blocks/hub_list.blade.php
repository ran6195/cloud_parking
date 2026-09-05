@php
    $hubPages = \App\Models\Page::query()->published()->inHub()->orderBy('title')->get();
@endphp
<section class="mx-auto max-w-6xl px-6 py-16">
    @if(! empty($data['heading']))
        <h2 class="text-2xl font-bold text-slate-900">{{ $data['heading'] }}</h2>
    @endif
    @if(! empty($data['intro']))
        <p class="mt-2 max-w-2xl text-slate-600">{{ $data['intro'] }}</p>
    @endif

    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($hubPages as $hubPage)
            @php $cover = $hubPage->getFirstMedia('cover'); @endphp
            <a href="{{ url('/'.$hubPage->slug) }}" class="group block overflow-hidden rounded-lg border border-slate-200">
                @if($cover)
                    <img src="{{ $cover->getUrl('thumb') }}" alt="{{ $hubPage->title }}" loading="lazy" class="h-40 w-full object-cover">
                @endif
                <div class="p-4">
                    <h3 class="font-semibold text-slate-900 group-hover:text-slate-700">{{ $hubPage->title }}</h3>
                    @if($hubPage->excerpt)
                        <p class="mt-1 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($hubPage->excerpt, 100) }}</p>
                    @endif
                </div>
            </a>
        @endforeach
    </div>
</section>
