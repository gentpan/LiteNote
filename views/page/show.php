@extends('layouts.front')

@section('content')
    <article class="page-detail">
        <h1>{{ $page->title }}</h1>
        <div class="page-content">
            {!! $page->content !!}
        </div>
    </article>
@endsection
