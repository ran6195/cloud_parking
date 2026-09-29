@php $items = $data['items'] ?? []; @endphp
@if(! empty($items))
    <x-section :data="$data" default-background="terra" compact>
        <h2 class="{{ empty($data['heading']) ? 'sr-only' : 'titolo-sezione mb-10' }}">{{ $data['heading'] ?? 'Perché sceglierci' }}</h2>
        <ul class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
            @foreach($items as $item)
                <x-feature :icon="$item['icon'] ?? null"
                           :title="$item['title'] ?? ''"
                           :description="\App\Support\SiteText::fill($item['description'] ?? null)" />
            @endforeach
        </ul>
    </x-section>
@endif
