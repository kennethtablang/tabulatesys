{{--
    PRISM brand mark: icon + wordmark. The wordmark uses currentColor, so it suits light and dark screens.
    $size: icon height in px (default 40). $stacked: icon above the text. $full: show the full name and subtitle.
--}}
@php($size = $size ?? 40)
<span class="prism-brand {{ ($stacked ?? false) ? 'is-stacked' : '' }}">
    {{-- Faceted crystal. Facet colours come from CSS so the gem stays visible on dark backgrounds. --}}
    <svg class="prism-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
        <path class="gem-f1" d="M12 22 L21 11 L28 22 Z"/>
        <path class="gem-f2" d="M21 11 L43 11 L36 22 L28 22 Z"/>
        <path class="gem-f3" d="M43 11 L52 22 L36 22 Z"/>
        <path class="gem-f4" d="M12 22 L28 22 L32 55 Z"/>
        <path class="gem-f5" d="M28 22 L36 22 L32 55 Z"/>
        <path class="gem-f6" d="M36 22 L52 22 L32 55 Z"/>
        <path class="gem-spark" d="M54 4 L55.6 8.4 L60 10 L55.6 11.6 L54 16 L52.4 11.6 L48 10 L52.4 8.4 Z"/>
    </svg>
    @unless($iconOnly ?? false)
    <span class="prism-text">
        <span class="prism-word">PRISM</span>
        @if($full ?? false)
            <span class="prism-full">{{ config('app.full_name') }}</span>
            <span class="prism-sub">{{ config('app.subtitle') }}</span>
        @elseif($subtitle ?? false)
            <span class="prism-sub">{{ $subtitle }}</span>
        @endif
    </span>
    @endunless
</span>
