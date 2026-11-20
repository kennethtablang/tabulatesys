@extends('layouts.app')

@section('title', 'Events')
@section('heading', 'Events')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active"><strong>Events</strong></li>
@endsection
@section('actions')
    <a href="{{ route('admin.events.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Add Event</a>
@endsection

@section('content')
<div class="m-b-md">
    <div class="btn-group">
        <a href="{{ route('admin.events.index') }}" class="btn btn-sm {{ request('status') ? 'btn-white' : 'btn-primary' }}">All</a>
        @foreach(\App\Models\Event::STATUSES as $value => $label)
            <a href="{{ route('admin.events.index', ['status' => $value]) }}" class="btn btn-sm {{ request('status') === $value ? 'btn-primary' : 'btn-white' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>

@if($events->isEmpty())
    <div class="ibox">
        <div class="ibox-content text-center p-xl">
            <i class="fa fa-trophy fa-4x text-muted"></i>
            <h3 class="m-t-md">No events yet</h3>
            <p class="text-muted">Create your first pageant or competition to start tabulating.</p>
            <a href="{{ route('admin.events.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Add Event</a>
        </div>
    </div>
@else
    <div class="row">
        @foreach($events as $event)
            <div class="col-md-6 col-xl-4">
                <div class="ibox event-card">
                    <div class="ibox-title">
                        <span class="badge {{ $event->statusBadge() }} float-right">{{ $event->statusLabel() }}</span>
                        <h5>{{ $event->typeLabel() }}</h5>
                    </div>
                    <div class="ibox-content">
                        <h3 class="m-b-xs"><a href="{{ route('admin.events.show', $event) }}">{{ $event->name }}</a></h3>
                        <div class="event-meta m-b-sm">
                            <div><i class="fa fa-calendar"></i> {{ $event->event_date?->format('F d, Y') ?? 'No date set' }}</div>
                            @if($event->venue)<div><i class="fa fa-map-marker"></i> {{ $event->venue }}</div>@endif
                            <div><i class="fa fa-calculator"></i> {{ $event->scoring_method === 'ranking' ? 'Rank sum' : 'Weighted average' }}</div>
                        </div>
                        <div class="row text-center m-t-sm">
                            <div class="col"><h4 class="m-b-none">{{ $event->contestants_count }}</h4><small class="text-muted">Contestants</small></div>
                            <div class="col"><h4 class="m-b-none">{{ $event->judges_count }}</h4><small class="text-muted">Judges</small></div>
                            <div class="col"><h4 class="m-b-none">{{ $event->rounds_count }}</h4><small class="text-muted">Rounds</small></div>
                            <div class="col"><h4 class="m-b-none">{{ $event->categories_count }}</h4><small class="text-muted">Categories</small></div>
                        </div>
                    </div>
                    <div class="ibox-footer">
                        <a href="{{ route('admin.events.show', $event) }}" class="btn btn-xs btn-white"><i class="fa fa-folder-open"></i> Open</a>
                        <a href="{{ route('admin.events.setup', $event) }}" class="btn btn-xs btn-white"><i class="fa fa-sliders"></i> Setup</a>
                        <a href="{{ route('admin.events.results', $event) }}" class="btn btn-xs btn-white"><i class="fa fa-bar-chart"></i> Results</a>
                        <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-xs btn-white float-right"><i class="fa fa-pencil"></i></a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
