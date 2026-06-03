@extends('layouts.front')

@section('content')
    <h2 class="section-title">文章归档</h2>
    <p class="section-desc">共 {{ $total }} 篇文章</p>
    @foreach($grouped as $month => $posts)
        <div class="archive-group">
            <h3>{{ $month }}</h3>
            <ul class="archive-list">
                @foreach($posts as $p)
                    <li>
                        <span class="archive-date">{{ substr($p['published_at'], 8, 2) }}日</span>
                        <a href="/post/{{ $p['slug'] }}.html">{{ $p['title'] }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
@endsection
