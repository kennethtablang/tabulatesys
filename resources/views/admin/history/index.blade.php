@extends('layouts.app')

@section('title', 'Tabulation History')
@section('heading', 'Tabulation History')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active"><strong>Tabulation History</strong></li>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center m-b-md" style="gap:8px">
    <div class="btn-group">
        @foreach(['events' => ['Events & Results', 'fa-trophy'], 'scores' => ['Score Changes', 'fa-pencil-square-o'], 'activity' => ['Activity Log', 'fa-list-alt']] as $key => [$label, $icon])
            <a href="{{ route('admin.history', ['tab' => $key] + ($eventId ? ['event' => $eventId] : [])) }}"
               class="btn btn-sm {{ $tab === $key ? 'btn-primary' : 'btn-white' }}"><i class="fa {{ $icon }}"></i> {{ $label }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.history') }}" class="form-inline ml-auto" style="gap:6px">
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if($tab === 'events')
            <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach(\App\Models\Event::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        @else
            <select name="event" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">All events</option>
                @foreach($eventOptions as $e)
                    <option value="{{ $e->id }}" @selected($eventId === $e->id)>{{ $e->name }}</option>
                @endforeach
            </select>
        @endif
        @if($tab === 'scores')
            <select name="judge" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">All judges</option>
                @foreach($judgeOptions as $j)
                    <option value="{{ $j->id }}" @selected(request()->integer('judge') === $j->id)>{{ $j->name }}</option>
                @endforeach
            </select>
        @endif
        @if($tab === 'activity')
            <select name="action" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">All actions</option>
                @foreach($actions as $value => $label)
                    <option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        @endif
    </form>
</div>

{{-- ---------------- Events & final results ---------------- --}}
@if($tab === 'events')
    <div class="ibox">
        <div class="ibox-content table-responsive">
            <table class="table table-hover m-b-none">
                <thead>
                <tr><th>Event</th><th>Date</th><th>Tabulation</th><th class="text-center">Contestants</th><th class="text-center">Judges</th><th class="text-center">Scores</th><th>Results</th><th class="text-right">Logs</th></tr>
                </thead>
                <tbody>
                @forelse($events as $event)
                    <tr>
                        <td><a href="{{ route('admin.events.show', $event) }}"><strong>{{ $event->name }}</strong></a><br><small class="text-muted">{{ $event->typeLabel() }} · {{ $event->venue ?: 'no venue' }}</small></td>
                        <td>{{ $event->event_date?->format('M d, Y') ?? '—' }}</td>
                        <td><span class="badge {{ $event->statusBadge() }}">{{ $event->statusLabel() }}</span></td>
                        <td class="text-center">{{ $event->contestants_count }}</td>
                        <td class="text-center">{{ $event->judges_count }}</td>
                        <td class="text-center">{{ number_format($event->scores_count) }}</td>
                        <td style="white-space:nowrap">
                            <a href="{{ route('admin.events.results', $event) }}" class="btn btn-xs btn-white"><i class="fa fa-bar-chart"></i> View</a>
                            @foreach($event->rounds as $round)
                                <div class="btn-group m-b-xs">
                                    <a href="{{ route('admin.rounds.print', $round) }}" target="_blank" class="btn btn-xs btn-white" title="Print {{ $round->name }} results"><i class="fa fa-print"></i> {{ \Illuminate\Support\Str::limit($round->name, 18) }}</a>
                                    <a href="{{ route('admin.rounds.export.pdf', $round) }}" class="btn btn-xs btn-white" title="{{ $round->name }} as PDF"><i class="fa fa-file-pdf-o"></i></a>
                                    <a href="{{ route('admin.rounds.export.excel', $round) }}" class="btn btn-xs btn-white" title="{{ $round->name }} as Excel"><i class="fa fa-file-excel-o"></i></a>
                                </div>
                            @endforeach
                        </td>
                        <td class="text-right" style="white-space:nowrap">
                            <a href="{{ route('admin.history', ['tab' => 'scores', 'event' => $event->id]) }}" class="btn btn-xs btn-white">Scores</a>
                            <a href="{{ route('admin.history', ['tab' => 'activity', 'event' => $event->id]) }}" class="btn btn-xs btn-white">Activity</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted p-lg">No events found.</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="m-t-md">{{ $events->links() }}</div>
        </div>
    </div>
@endif

{{-- ---------------- Score change log ---------------- --}}
@if($tab === 'scores')
    <div class="ibox">
        <div class="ibox-title">
            <h5>Every score entered or changed by the judges</h5>
            <div class="ibox-tools"><small class="text-muted">Fast typing on one cell is merged into one entry.</small></div>
        </div>
        <div class="ibox-content table-responsive">
            <table class="table table-sm table-hover m-b-none">
                <thead>
                <tr><th>Time</th><th>Judge</th><th>Event · Category</th><th>Contestant</th><th>Criterion</th><th class="text-center">Change</th><th>Via</th></tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td style="white-space:nowrap">{{ $log->updated_at->format('M d, h:i:s A') }}</td>
                        <td>{{ $log->judge?->name ?? '(deleted)' }}</td>
                        <td>{{ $log->event?->name }} · <b>{{ $log->category?->name }}</b></td>
                        <td>@if($log->contestant)#{{ $log->contestant->number }} {{ $log->contestant->name }} @if($log->contestant->division)<span class="badge badge-light">{{ $log->contestant->division }}</span>@endif @endif</td>
                        <td>{{ $log->criterion?->name }}</td>
                        <td class="text-center" style="white-space:nowrap">
                            <span class="text-muted">{{ $log->old_score === null ? '—' : num($log->old_score) }}</span>
                            <i class="fa fa-long-arrow-right m-l-xs m-r-xs"></i>
                            @if($log->new_score === null)
                                <span class="text-danger">cleared</span>
                            @else
                                <b>{{ num($log->new_score) }}</b>
                            @endif
                        </td>
                        <td><small class="text-muted">{{ $log->source === 'sheet' ? 'Save/Submit' : 'Typing' }}@if($log->ip_address) · {{ $log->ip_address }}@endif</small></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted p-lg">No score changes recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="m-t-md">{{ $logs->links() }}</div>
        </div>
    </div>
@endif

{{-- ---------------- Activity log ---------------- --}}
@if($tab === 'activity')
    <div class="ibox">
        <div class="ibox-content table-responsive">
            <table class="table table-sm table-hover m-b-none">
                <thead>
                <tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>Event</th><th>IP address</th></tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td style="white-space:nowrap">{{ $log->created_at->format('M d, h:i:s A') }}</td>
                        <td>{{ $log->user_name ?? '—' }}</td>
                        <td><span class="badge {{ $log->action === 'login_failed' ? 'badge-danger' : 'badge-light' }}">{{ $log->actionLabel() }}</span></td>
                        <td>{{ $log->description }}</td>
                        <td>{{ $log->event?->name ?? '—' }}</td>
                        <td><small class="text-muted">{{ $log->ip_address }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted p-lg">No activity recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="m-t-md">{{ $logs->links() }}</div>
        </div>
    </div>
@endif
@endsection
