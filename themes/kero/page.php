@extends('layouts.front')

@section('content')
    <article class="kero-doc page-detail">
        <header class="kero-doc-header">
            <p class="kero-section-label">Page</p>
            <h1 class="kero-hero-title">{{ $page->title }}</h1>
        </header>
        <div class="kero-doc-body page-content">
            {!! $page->content !!}
        </div>
    </article>
@endsection
