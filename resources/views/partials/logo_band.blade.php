{{-- Row of logos (school, LGU, sponsors...). $logos: collection of Logo; $title / $subtitle optional. --}}
@if(isset($logos) && $logos->isNotEmpty())
    @php($size = \App\Models\Setting::get('logos.size', 'medium'))
    <div class="logo-band logo-band-{{ $size }} {{ $class ?? '' }}">
        <div class="logo-band-logos">
            @foreach($logos as $logo)
                <img src="{{ $logo->url() }}" alt="{{ $logo->name }}" title="{{ $logo->name }}" style="--logo-scale: {{ max(40, min(250, (int) ($logo->scale ?? 100))) / 100 }}">
            @endforeach
        </div>
        @if(! empty($title))
            <div class="logo-band-title">{{ $title }}</div>
            @if(! empty($subtitle))<div class="logo-band-subtitle">{{ $subtitle }}</div>@endif
        @endif
    </div>
@endif
