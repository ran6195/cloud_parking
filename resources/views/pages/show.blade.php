@extends('layouts.app')

@section('content')
    @unless(collect($page->blocks ?? [])->contains('type', 'hero'))
        <div class="bg-fondo text-carta">
            <div class="contenitore py-14 sm:py-20">
                <h1 class="font-display text-4xl leading-[1.05] font-extrabold tracking-[-1px] sm:text-5xl">{{ $page->title }}</h1>
                @if($page->excerpt)
                    <p class="mt-4 max-w-2xl text-lg text-testo-chiaro">{{ $page->excerpt }}</p>
                @endif
            </div>
        </div>
    @endunless

    @foreach($page->blocks ?? [] as $block)
        @includeFirst(["blocks.{$block['type']}", 'blocks.missing'], ['data' => $block['data'] ?? [], 'page' => $page])
    @endforeach
@endsection
