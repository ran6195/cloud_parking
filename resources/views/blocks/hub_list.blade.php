@php
    $hubPages = \App\Models\Page::query()->published()->inHub()->orderBy('title')->get();
@endphp
<x-section :data="$data" default-background="terra">
    @if(! empty($data['heading']))
        <h2 class="titolo-sezione">{{ $data['heading'] }}</h2>
    @endif
    @if(! empty($data['intro']))
        <p class="mt-4 max-w-2xl text-lg text-testo-soft">{{ $data['intro'] }}</p>
    @endif

    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($hubPages as $hubPage)
            @php $cover = $hubPage->getFirstMedia('cover'); @endphp
            <a href="{{ url('/'.$hubPage->slug) }}" class="group flex flex-col overflow-hidden rounded-card bg-carta">
                @if($cover)
                    <img src="{{ $cover->getUrl('thumb') }}" alt="" width="400" height="300" loading="lazy" class="aspect-[4/3] w-full object-cover">
                @endif
                <div class="p-6">
                    <h3 class="font-display text-xl leading-tight font-bold group-hover:underline">{{ $hubPage->title }}</h3>
                    @if($hubPage->excerpt)
                        <p class="mt-2 text-testo-soft">{{ \Illuminate\Support\Str::limit($hubPage->excerpt, 110) }}</p>
                    @endif
                </div>
            </a>
        @endforeach
    </div>
</x-section>
