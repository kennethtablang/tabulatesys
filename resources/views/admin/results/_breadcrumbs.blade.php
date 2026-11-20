@if(auth()->user()->isAdmin())
    <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Events</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.events.show', $event) }}">{{ $event->name }}</a></li>
@else
    <li class="breadcrumb-item"><a href="{{ route('tabulator.dashboard') }}">Live Events</a></li>
    <li class="breadcrumb-item">{{ $event->name }}</li>
@endif
