@php
    $items = $data['items'] ?? [];
    $parking = app(\App\Settings\ParkingSettings::class);
    $showRating = ($data['show_rating'] ?? true) && $parking->google_rating && $parking->google_reviews_count;
@endphp
@if(! empty($items))
    <x-section :data="$data" default-background="terra">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            @if(! empty($data['heading']))
                <h2 class="titolo-sezione">{{ $data['heading'] }}</h2>
            @endif
            @if($showRating)
                <p class="flex items-center gap-2 text-lg">
                    <x-icona name="star" class="size-6 fill-segnale text-fondo" />
                    <span><strong class="font-display text-2xl">{{ number_format($parking->google_rating, 1, ',', '') }}</strong> su 5 da {{ $parking->google_reviews_count }} recensioni Google</span>
                </p>
            @endif
        </div>

        <div class="mt-10 grid gap-5 md:grid-cols-3">
            @foreach($items as $item)
                <x-review-card :quote="$item['quote'] ?? ''" :author="$item['author'] ?? ''" :role="$item['role'] ?? null" />
            @endforeach
        </div>
    </x-section>
@endif
