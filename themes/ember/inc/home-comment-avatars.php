@php
    $avatarPeople = [];
    $avatarIdentityKeys = [];
    foreach (array_reverse(array_values($avatarComments ?? [])) as $avatarComment) {
        $avatarName = trim((string)($avatarComment->nickname ?? '')) ?: '访客';
        $avatarEmail = strtolower(trim((string)($avatarComment->email ?? '')));
        $avatarIdentityKey = $avatarEmail !== ''
            ? 'email:' . $avatarEmail
            : 'name:' . mb_strtolower($avatarName);
        if (isset($avatarIdentityKeys[$avatarIdentityKey])) {
            continue;
        }
        $avatarIdentityKeys[$avatarIdentityKey] = true;
        $avatarPeople[] = [
            'name' => $avatarName,
            'url' => $avatarComment->getAvatarUrl(48),
        ];
    }
    $avatarUniqueCount = count($avatarPeople);
    $avatarPeople = array_slice($avatarPeople, 0, 6);
    $avatarPeopleCount = count($avatarPeople);
    $avatarRemainingCount = max(0, $avatarUniqueCount - $avatarPeopleCount);
    $avatarCircleCount = $avatarPeopleCount + ($avatarRemainingCount > 0 ? 1 : 0);
    $avatarGroupLabel = $avatarPeopleCount > 0
        ? '最新评论：' . implode('、', array_column($avatarPeople, 'name'))
            . ($avatarRemainingCount > 0 ? '，另有 ' . $avatarRemainingCount . ' 位评论者' : '')
        : '';
    $avatarGroupWidth = $avatarCircleCount > 0 ? 24 + (($avatarCircleCount - 1) * 14) : 0;
@endphp
@if($avatarPeopleCount > 0)
    <span class="home-comment-avatars t-avatar-group" role="group" tabindex="0" aria-label="{{ $avatarGroupLabel }}" style="--avatar-group-width: {{ $avatarGroupWidth }}px">
        @foreach($avatarPeople as $avatarIndex => $avatarPerson)
            <span class="home-comment-avatar t-avatar" aria-hidden="true" style="--avatar-offset: {{ $avatarIndex * 14 }}px; --avatar-layer: {{ $avatarIndex + 1 }}">
                <img src="{{ $avatarPerson['url'] }}" alt="" width="24" height="24" loading="lazy" decoding="async">
            </span>
        @endforeach
        @if($avatarRemainingCount > 0)
            <span class="home-comment-avatar home-comment-avatar-more t-avatar" aria-hidden="true" style="--avatar-offset: {{ $avatarPeopleCount * 14 }}px; --avatar-layer: {{ $avatarCircleCount }}">+{{ $avatarRemainingCount }}</span>
        @endif
    </span>
@endif
