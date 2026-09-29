@php $items = $data['items'] ?? []; @endphp
<x-section :data="$data" default-background="terra" default-anchor="come">
    <div class="max-w-2xl">
        <h2 class="titolo-sezione">{{ $data['heading'] ?? '' }}</h2>
        @if(! empty($data['subheading']))
            <p class="mt-4 text-lg text-testo-soft">{{ \App\Support\SiteText::fill($data['subheading']) }}</p>
        @endif
    </div>

    @if(! empty($items))
        <ol class="mt-10 grid gap-4 lg:grid-cols-4 lg:gap-5">
            @foreach($items as $item)
                <x-step-card :number="$loop->iteration"
                             :title="$item['title'] ?? ''"
                             :description="\App\Support\SiteText::fill($item['description'] ?? null)" />
            @endforeach
        </ol>
    @endif
</x-section>
