@extends('layouts.app')

@section('content')
    @foreach($page->blocks ?? [] as $block)
        @includeFirst(["blocks.{$block['type']}", 'blocks.missing'], ['data' => $block['data'] ?? [], 'page' => $page])
    @endforeach
@endsection
