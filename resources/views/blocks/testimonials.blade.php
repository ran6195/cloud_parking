@php $items = $data['items'] ?? []; @endphp
@if(! empty($items))
    <section class="mx-auto max-w-6xl px-6 py-16">
        <div class="grid gap-8 md:grid-cols-2">
            @foreach($items as $item)
                <blockquote class="rounded-lg bg-slate-50 p-6">
                    <p class="text-slate-700">&ldquo;{{ $item['quote'] ?? '' }}&rdquo;</p>
                    <footer class="mt-4 flex items-center gap-3">
                        @if(! empty($item['avatar']))
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item['avatar']) }}"
                                 alt="{{ $item['author'] ?? '' }}" class="h-10 w-10 rounded-full object-cover">
                        @endif
                        <div>
                            <p class="font-semibold text-slate-900">{{ $item['author'] ?? '' }}</p>
                            @if(! empty($item['role']))
                                <p class="text-sm text-slate-500">{{ $item['role'] }}</p>
                            @endif
                        </div>
                    </footer>
                </blockquote>
            @endforeach
        </div>
    </section>
@endif
