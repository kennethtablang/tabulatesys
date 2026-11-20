@php($isAdmin = auth()->user()->isAdmin())
@php($tabs = array_merge($isAdmin ? [
    'admin.events.show' => ['Overview', 'fa-info-circle'],
    'admin.events.setup' => ['Rounds & Criteria', 'fa-sliders'],
    'admin.contestants.index' => ['Contestants', 'fa-user'],
    'admin.events.judges' => ['Judges & Tabulator', 'fa-gavel'],
] : [], [
    'admin.events.monitor' => ['Scoring Monitor', 'fa-desktop'],
    'admin.events.results' => ['Results', 'fa-bar-chart'],
]))
<div class="ibox m-b-md">
    <div class="ibox-content p-sm d-flex flex-wrap align-items-center" style="gap:6px">
        <div class="m-r-md">
            <span class="badge {{ $event->statusBadge() }}">{{ $event->statusLabel() }}</span>
            <strong class="m-l-xs">{{ $event->name }}</strong>
        </div>
        @foreach($tabs as $route => [$label, $icon])
            <a href="{{ route($route, $event) }}" class="btn btn-sm {{ request()->routeIs($route) ? 'btn-primary' : 'btn-white' }}">
                <i class="fa {{ $icon }}"></i> {{ $label }}
            </a>
        @endforeach
        @if($isAdmin)
        <div class="ml-auto">
            <form method="POST" action="{{ route('admin.events.status', $event) }}" class="d-inline">
                @csrf @method('PATCH')
                @if($event->isActive())
                    <button name="status" value="draft" class="btn btn-sm btn-warning" title="Pause: judges can no longer score, the event is hidden from them"><i class="fa fa-pause"></i> Set Inactive</button>
                    <button name="status" value="completed" class="btn btn-sm btn-success" title="Finish the event and keep it in Tabulation History"><i class="fa fa-flag-checkered"></i> Mark Completed</button>
                @else
                    <button name="status" value="ongoing" class="btn btn-sm btn-primary" title="Judges can score open categories, live results run"><i class="fa fa-play"></i> Set Active</button>
                @endif
            </form>
        </div>
        @endif
    </div>
</div>
