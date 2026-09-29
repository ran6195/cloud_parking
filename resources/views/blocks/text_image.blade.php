@php
    $imageUrl = \App\Support\Uploads::url($data['image'] ?? null);
    $imageFirst = ($data['image_position'] ?? 'right') === 'left';
@endphp
<x-section :data="$data" default-background="carta">
    <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
        <div class="{{ $imageFirst ? 'lg:order-2' : '' }}">
            @if(! empty($data['heading']))
                <h2 class="titolo-sezione">{{ $data['heading'] }}</h2>
            @endif
            @if(! empty($data['content']))
                <div class="prosa mt-5 text-lg leading-relaxed text-testo-soft">{!! $data['content'] !!}</div>
            @endif
        </div>
        @if($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $data['heading'] ?? '' }}" class="w-full rounded-card" width="800" height="600" loading="lazy">
        @endif
    </div>
</x-section>
