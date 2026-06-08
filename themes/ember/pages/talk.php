@extends('layouts.front')

@section('content')
    <section class="talk-list">
        <h2 class="section-title">滔客</h2>
        @include('partials.talk-publish-form')
        @if(empty($list))
            <p class="empty">还没有滔客</p>
        @endif
        <div class="js-list-items">
        @foreach($list as $s)
            @include('partials.talk-card')
        @endforeach
        </div>
        {!! $paginator ?? '' !!}
    </section>
@endsection
