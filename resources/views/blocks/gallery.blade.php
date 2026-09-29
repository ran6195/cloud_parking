@php $images = $data['images'] ?? []; @endphp
@if(! empty($images))
    <x-section :data="$data" default-background="terra">
        @if(! empty($data['heading']))
            <h2 class="titolo-sezione mb-10">{{ $data['heading'] }}</h2>
        @endif
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
            @foreach($images as $image)
                <img src="{{ \App\Support\Uploads::url($image) }}"
                     alt="" width="400" height="300" loading="lazy" class="aspect-[4/3] w-full rounded-card object-cover">
            @endforeach
        </div>
    </x-section>
@endif
