{{-- Contestant label, following the display settings (photo, name, details). $size: small|medium|large|xl --}}
@php($size = $size ?? $display['photo_size'])
<div class="sheet-contestant {{ $stacked ?? false ? 'is-stacked' : '' }}">
    @if($display['show_photos'])
        <img src="{{ $c->photoUrl() }}" class="sheet-photo sheet-photo-{{ $size }}" alt="">
    @endif
    <div class="sheet-contestant-text">
        <span class="sheet-number">#{{ $c->number }}</span>
        @if($display['show_names'])
            <strong class="sheet-name">{{ $c->name }}</strong>
        @else
            <strong class="sheet-name">Contestant No. {{ $c->number }}</strong>
        @endif
        @if($display['show_details'])
            @if($c->division)<span class="badge badge-light">{{ $c->division }}</span>@endif
            @if($display['show_names'] && $c->representing)<small class="text-muted d-block">{{ $c->representing }}</small>@endif
        @endif
    </div>
</div>
