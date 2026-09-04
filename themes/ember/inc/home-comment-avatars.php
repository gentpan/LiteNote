@php
    $avatarPeople = [];
    $seenAvatarPeople = [];
    foreach (array_reverse(array_values($avatarComments ?? [])) as $avatarComment) {
        $avatarName = trim((string)($avatarComment->nickname ?? '')) ?: '访客';
        $avatarEmail = strtolower(trim((string)($avatarComment->email ?? '')));
        $avatarIdentity = $avatarEmail !== '' ? 'email:' . $avatarEmail : 'name:' . $avatarName;
        if (isset($seenAvatarPeople[$avatarIdentity])) {
            continue;
        }
        $seenAvatarPeople[$avatarIdentity] = true;
        $avatarPeople[] = [
            'name' => $avatarName,
            'url' => $avatarComment->getAvatarUrl(48),
        ];
        if (count($avatarPeople) >= 4) {
            break;
        }
    }
    $avatarPeopleCount = count($avatarPeople);
    $avatarGroupLabel = $avatarPeopleCount > 0
        ? '最近评论者：' . implode('、', array_column($avatarPeople, 'name'))
        : '';
    $avatarGroupWidth = $avatarPeopleCount > 0 ? 24 + (($avatarPeopleCount - 1) * 21) : 0;
@endphp
@if($avatarPeopleCount > 0)
    <span class="home-comment-avatars" role="group" tabindex="0" aria-label="{{ $avatarGroupLabel }}" style="--avatar-group-width: {{ $avatarGroupWidth }}px">
        @foreach($avatarPeople as $avatarIndex => $avatarPerson)
            <span class="home-comment-avatar" aria-hidden="true" style="--avatar-offset: {{ $avatarIndex * 14 }}px; --avatar-open-offset: {{ $avatarIndex * 21 }}px; --avatar-delay: {{ $avatarIndex * 35 }}ms; --avatar-layer: {{ $avatarIndex + 1 }}">
                <img src="{{ $avatarPerson['url'] }}" alt="" width="24" height="24" loading="lazy" decoding="async">
            </span>
        @endforeach
    </span>
@endif
