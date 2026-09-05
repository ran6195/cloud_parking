@php
    $imageUrl = ! empty($data['image']) ? \Illuminate\Support\Facades\Storage::disk('public')->url($data['image']) : null;
    $imageFirst = ($data['image_position'] ?? 'right') === 'left';
@endphp
<section class="mx-auto max-w-6xl px-6 py-16">
    <div class="grid items-center gap-10 md:grid-cols-2">
        <div class="{{ $imageFirst ? 'md:order-2' : '' }}">
            @if(! empty($data['heading']))
                <h2 class="text-2xl font-bold text-slate-900">{{ $data['heading'] }}</h2>
            @endif
            @if(! empty($data['content']))
                <div class="prose prose-slate mt-4 max-w-none">{!! $data['content'] !!}</div>
            @endif
        </div>
        @if($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $data['heading'] ?? '' }}" class="w-full rounded-lg" loading="lazy">
        @endif
    </div>
</section>
