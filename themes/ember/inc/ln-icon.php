@php
    /**
     * Render an animated LiteNote icon host.
     *
     * @var string $name Icon key (ln-icons registry)
     * @var string $class Extra CSS classes
     * @var bool $filled Filled style (e.g. liked heart)
     * @var string $trigger hover|click|both
     */
    $name = (string)($name ?? '');
    $class = trim((string)($class ?? ''));
    $filled = !empty($filled);
    $trigger = (string)($trigger ?? 'hover');
    $classes = trim('ln-icon ' . $class);
@endphp
@if($name !== '')
<span class="{{ $classes }}" data-ln-icon="{{ $name }}"@if($filled) data-ln-filled="1"@endif data-ln-icon-trigger="{{ $trigger }}" aria-hidden="true"></span>
@endif
