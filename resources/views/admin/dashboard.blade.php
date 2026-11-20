@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('actions')
    <a href="{{ route('admin.events.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Add Event</a>
@endsection

@section('content')
<div class="row">
    @foreach([
        ['Events', $stats['events'], 'fa-trophy', 'navy-bg'],
        ['Active Now', $stats['ongoing'], 'fa-play-circle', 'lazur-bg'],
        ['Contestants', $stats['contestants'], 'fa-user', 'yellow-bg'],
        ['Judges', $stats['judges'], 'fa-gavel', 'red-bg'],
    ] as [$label, $value, $icon, $bg])
        <div class="col-6 col-lg-3">
            <div class="widget style1 {{ $bg }}">
                <div class="row">
                    <div class="col-4"><i class="fa {{ $icon }} fa-4x"></i></div>
                    <div class="col-8 text-right">
                        <span>{{ $label }}</span>
                        <h2 class="font-bold">{{ $value }}</h2>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row">
    <div class="col-lg-8">
        <h3 class="m-b-sm">Active events</h3>
        @forelse($ongoing as $event)
            @php($categories = $event->rounds->flatMap->categories)
            @php($open = $categories->where('status', 'open'))
            <div class="ibox">
                <div class="ibox-title">
                    <h5><a href="{{ route('admin.events.show', $event) }}">{{ $event->name }}</a> <small class="m-l-sm">{{ $event->typeLabel() }}</small></h5>
                    <div class="ibox-tools">
                        <a href="{{ route('admin.events.monitor', $event) }}" class="btn btn-xs btn-white"><i class="fa fa-desktop"></i> Monitor</a>
                        <a href="{{ route('admin.events.results', $event) }}" class="btn btn-xs btn-primary"><i class="fa fa-bar-chart"></i> Results</a>
                    </div>
                </div>
                <div class="ibox-content">
                    @if($open->isEmpty())
                        <p class="text-muted m-b-none">No category is open for scoring right now.
                            <a href="{{ route('admin.events.show', $event) }}">Open a category</a></p>
                    @else
                        @foreach($open as $category)
                            @php($p = collect($event->progress[$category->id] ?? []))
                            @php($done = $p->where('submitted', true)->count())
                            @php($entered = $p->sum('entered')) @php($expected = max(1, $p->sum('expected')))
                            <div class="m-b-sm">
                                <div class="d-flex"><strong>{{ $category->name }}</strong>
                                    <small class="ml-auto">{{ $done }}/{{ $p->count() }} judges submitted · {{ round($entered / $expected * 100) }}% scored</small></div>
                                <div class="progress progress-small"><div class="progress-bar" style="width: {{ round($entered / $expected * 100) }}%"></div></div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        @empty
            <div class="ibox"><div class="ibox-content text-center text-muted p-lg">
                No event is active. Open an event and click <b>Set Active</b> to let judges start scoring.
            </div></div>
        @endforelse
    </div>

    <div class="col-lg-4">
        <div class="ibox">
            <div class="ibox-title"><h5>Recently created</h5></div>
            <div class="ibox-content no-padding">
                <ul class="list-group">
                    @forelse($recent as $event)
                        <li class="list-group-item">
                            <span class="badge {{ $event->statusBadge() }} float-right">{{ $event->statusLabel() }}</span>
                            <a href="{{ route('admin.events.show', $event) }}">{{ $event->name }}</a><br>
                            <small class="text-muted">{{ $event->typeLabel() }} · {{ $event->event_date?->format('M d, Y') ?? 'no date' }}</small>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No events yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="ibox">
            <div class="ibox-content">
                <h5>Total scores recorded</h5>
                <h1 class="no-margins">{{ number_format($stats['scores']) }}</h1>
                <small class="text-muted">criterion scores across all events</small>
            </div>
        </div>
    </div>
</div>
@endsection
