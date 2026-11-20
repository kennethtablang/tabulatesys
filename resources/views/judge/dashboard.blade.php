@extends('layouts.app')

@section('title', 'My Events')
@section('heading', 'My Events')

@section('content')
@if($events->isEmpty())
    <div class="ibox"><div class="ibox-content text-center p-xl">
        <i class="fa fa-gavel fa-4x text-muted"></i>
        <h3 class="m-t-md">No active events</h3>
        <p class="text-muted">You will see events here once the administrator assigns you and starts the event.</p>
    </div></div>
@else
    <div class="row">
        @foreach($events as $event)
            @php($open = $event->rounds->flatMap->categories->where('status', 'open'))
            <div class="col-md-6 col-xl-4">
                <div class="ibox event-card">
                    <div class="ibox-title">
                        <span class="badge {{ $event->statusBadge() }} float-right">{{ $event->statusLabel() }}</span>
                        <h5>{{ $event->typeLabel() }}</h5>
                    </div>
                    <div class="ibox-content">
                        @include('partials.logo_band', ['logos' => $event->displayLogos(), 'class' => 'logo-band-card'])
                        <h3><a href="{{ route('judge.event', $event) }}">{{ $event->name }}</a></h3>
                        <div class="event-meta">
                            <div><i class="fa fa-calendar"></i> {{ $event->event_date?->format('F d, Y') ?? '—' }}</div>
                            @if($event->venue)<div><i class="fa fa-map-marker"></i> {{ $event->venue }}</div>@endif
                        </div>
                        <p class="m-t-sm m-b-none">
                            @if($open->isNotEmpty())
                                <span class="text-navy"><i class="fa fa-circle"></i> Open for scoring: <b>{{ $open->pluck('name')->join(', ') }}</b></span>
                            @else
                                <span class="text-muted">Waiting for the administrator to open a category.</span>
                            @endif
                        </p>
                    </div>
                    <div class="ibox-footer">
                        <a href="{{ route('judge.event', $event) }}" class="btn btn-primary btn-sm btn-block"><i class="fa fa-pencil"></i> Go to score sheets</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection

@push('scripts')
<script>setTimeout(function () { location.reload(); }, 30000);</script>
@endpush
