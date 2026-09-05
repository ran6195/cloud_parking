@php $images = $data['images'] ?? []; @endphp
@if(! empty($images))
    <section class="mx-auto max-w-6xl px-6 py-16">
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
            @foreach($images as $image)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image) }}"
                     alt="" loading="lazy" class="h-48 w-full rounded-lg object-cover">
            @endforeach
        </div>
    </section>
@endif
