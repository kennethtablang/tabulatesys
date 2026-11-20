@extends('layouts.app')

@section('title', 'Live Events')
@section('heading', 'Live Events')

@section('content')
@forelse($events as $event)
    @php($categories = $event->rounds->flatMap->categories)
    @php($open = $categories->where('status', 'open'))
    <div class="ibox">
        @include('partials.event_banner', ['event' => $event, 'class' => 'event-banner-card'])
        <div class="ibox-title">
            <h5><span class="badge {{ $event->statusBadge() }}">{{ $event->statusLabel() }}</span> {{ $event->name }} <small class="m-l-sm">{{ $event->typeLabel() }} · {{ $event->judges->count() }} judge(s)</small></h5>
            <div class="ibox-tools">
                <a href="{{ route('admin.events.monitor', $event) }}" class="btn btn-xs btn-white"><i class="fa fa-desktop"></i> Scoring Monitor</a>
                <a href="{{ route('admin.events.results', $event) }}" class="btn btn-xs btn-primary"><i class="fa fa-bar-chart"></i> Live Results</a>
            </div>
        </div>
        <div class="ibox-content">
            @unless($event->isActive())
                <div class="alert alert-warning m-b-sm"><i class="fa fa-pause"></i> Tabulation for this event is <b>{{ $event->statusLabel() }}</b>. Judges can't score until the administrator sets it to Active.</div>
            @endunless
            @if($open->isEmpty())
                <p class="text-muted m-b-none">No category is open for scoring right now.
                    <a href="{{ route('admin.events.monitor', $event) }}">Open one from the Scoring Monitor.</a></p>
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
        No event is active yet. The administrator needs to set an event's tabulation to Active before scoring can begin.
    </div></div>
@endforelse
@endsection

@push('scripts')
<script>setTimeout(function () { location.reload(); }, 15000);</script>
@endpush
