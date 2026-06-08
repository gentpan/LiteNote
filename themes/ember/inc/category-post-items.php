@foreach($posts as $index => $post)
    @php
        $item = $post;
        $index = ($offset ?? 0) + $index;
    @endphp
    @include('partials.category-post-card')
@endforeach
