@php
    $commentAuthorName = (string)($commentAuthor->nickname ?? '读者');
    $commentAuthorWebsite = trim((string)($commentAuthor->website ?? ''));
    if ($commentAuthorWebsite !== '' && !preg_match('~^https?://~i', $commentAuthorWebsite)) {
        $commentAuthorWebsite = 'https://' . $commentAuthorWebsite;
    }
    $commentAuthorScheme = strtolower((string)parse_url($commentAuthorWebsite, PHP_URL_SCHEME));
    if (
        $commentAuthorWebsite === ''
        || !in_array($commentAuthorScheme, ['http', 'https'], true)
        || filter_var($commentAuthorWebsite, FILTER_VALIDATE_URL) === false
    ) {
        $commentAuthorWebsite = '';
    }
@endphp
@if($commentAuthorWebsite !== '')
    <a class="comment-author-link" href="{{ $commentAuthorWebsite }}" target="_blank" rel="nofollow noopener">
        <strong>{{ $commentAuthorName }}</strong>
    </a>
@else
    <strong>{{ $commentAuthorName }}</strong>
@endif
