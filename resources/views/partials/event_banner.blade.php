{{-- Event banner. $event, optional $class: event-banner-slim | event-banner-card --}}
@if($event && $event->banner)
    <div class="event-banner {{ $class ?? '' }}">
        <img src="{{ $event->bannerUrl() }}" alt="{{ $event->name }} banner">
    </div>
@endif
