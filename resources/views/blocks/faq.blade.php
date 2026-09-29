@php
    use App\Support\SiteText;

    $items = $data['items'] ?? [];
    $uid = 'faq-'.substr(md5(json_encode($items)), 0, 6);
@endphp
@if(! empty($items))
    <x-section :data="$data" default-background="carta" default-anchor="domande">
        <div class="grid gap-10 lg:grid-cols-3 lg:gap-16">
            <div>
                <h2 class="titolo-sezione">{{ $data['heading'] ?? 'Domande frequenti' }}</h2>
                @if(! empty($data['intro']))
                    <p class="mt-4 text-lg text-testo-soft">{{ SiteText::fill($data['intro']) }}</p>
                @endif
            </div>

            <div class="border-t border-bordo lg:col-span-2" x-data="{ aperta: null }">
                @foreach($items as $item)
                    <x-faq-item :id="$uid.'-'.$loop->index" :index="$loop->index"
                                :question="$item['question'] ?? ''"
                                :answer="SiteText::fill($item['answer'] ?? '')" />
                @endforeach
            </div>
        </div>
    </x-section>
@endif
