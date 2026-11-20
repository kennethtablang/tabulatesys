@extends('layouts.app')

@section('title', 'Judges & Tabulator · '.$event->name)
@section('heading', 'Judges & Tabulator')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Events</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.events.show', $event) }}">{{ $event->name }}</a></li>
    <li class="breadcrumb-item active"><strong>Judges &amp; Tabulator</strong></li>
@endsection

@section('actions')
    <form method="POST" action="{{ route('admin.judges.refresh') }}" class="d-inline js-confirm"
          data-confirm="Reload the screens of this event's judges now? Unsaved scores are saved first." data-type="info" data-color="#1ab394" data-button="Refresh now">
        @csrf <input type="hidden" name="event" value="{{ $event->id }}">
        <button class="btn btn-white"><i class="fa fa-refresh"></i> Refresh judges' screens</button>
    </form>
@endsection

@section('content')
@include('admin.events._nav')

<div class="row">
    {{-- ---------------- Judges ---------------- --}}
    <div class="col-xl-7">
        <div class="ibox">
            <div class="ibox-title"><h5><i class="fa fa-gavel"></i> Judges of this event <span class="badge badge-primary m-l-xs">{{ $event->judges->count() }}</span></h5></div>
            <div class="ibox-content table-responsive">
                @if($event->judges->isEmpty())
                    <p class="text-muted m-b-none">No judges yet. Create their accounts below.</p>
                @else
                    <table class="table table-hover m-b-none">
                        <thead><tr><th>Judge</th><th>Username</th><th>Status</th><th class="text-right"></th></tr></thead>
                        <tbody>
                        @foreach($event->judges as $judge)
                            <tr>
                                <td><strong>{{ $judge->name }}</strong></td>
                                <td><code>{{ $judge->username }}</code></td>
                                <td>{!! $judge->is_active ? '<span class="text-navy">Active</span>' : '<span class="text-danger">Disabled</span>' !!}
                                    @php($online = \App\Support\LiveDisplay::isOnline($judge))
                                    <br><small class="{{ $online ? 'text-navy' : 'text-muted' }}"><span class="online-dot {{ $online ? 'is-online' : '' }}"></span> {{ $online ? 'Online now' : 'Offline' }}</small></td>
                                <td class="text-right" style="white-space:nowrap">
                                    <form method="POST" action="{{ route('admin.users.reset-password', $judge) }}" class="d-inline js-confirm"
                                          data-confirm="Generate a new password for {{ $judge->name }}? The old password will stop working." data-type="info" data-color="#1ab394" data-button="Generate">
                                        @csrf
                                        <button class="btn btn-xs btn-white" title="New password and login slip"><i class="fa fa-key"></i> New password</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.events.judges.detach', [$event, $judge]) }}" class="d-inline js-confirm"
                                          data-confirm="Remove {{ $judge->name }} from {{ $event->name }}? Their scores stay saved but no longer count.">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-xs btn-white text-danger" title="Remove from this event"><i class="fa fa-times"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="ibox">
            <div class="ibox-title"><h5><i class="fa fa-user-plus"></i> Create judge accounts</h5></div>
            <div class="ibox-content">
                <form method="POST" action="{{ route('admin.events.judges.generate', $event) }}">
                    @csrf
                    <div class="form-group">
                        <label>Judge names <small class="text-muted">(one per line)</small></label>
                        <textarea name="names" rows="4" class="form-control" placeholder="Judge Maria Santos&#10;Judge Jose Reyes&#10;Judge Ana Cruz" required>{{ old('names') }}</textarea>
                        <small class="text-muted">A username and password are generated for each judge, e.g. <code>ev{{ $event->id }}-judge1</code>. You get printable login slips right away.</small>
                    </div>
                    <button class="btn btn-primary"><i class="fa fa-magic"></i> Generate judge accounts</button>
                </form>
            </div>
        </div>

        @php($assigned = $event->judges->pluck('id')->all())
        @if($judges->isNotEmpty())
            <form method="POST" action="{{ route('admin.events.judges.update', $event) }}">
                @csrf @method('PUT')
                <div class="ibox">
                    <div class="ibox-title">
                        <h5>Or pick existing judge accounts</h5>
                        <div class="ibox-tools"><small class="text-muted">A judge can judge several events.</small></div>
                    </div>
                    <div class="ibox-content">
                        <div class="row">
                            @foreach($judges as $judge)
                                <div class="col-md-6">
                                    <label class="check-tile">
                                        <input type="checkbox" name="judges[]" value="{{ $judge->id }}" @checked(in_array($judge->id, $assigned))>
                                        <div class="tab-avatar" style="width:36px;height:36px;font-size:16px">{{ strtoupper(substr($judge->name, 0, 1)) }}</div>
                                        <span>
                                            <b>{{ $judge->name }}</b> @unless($judge->is_active)<span class="badge badge-danger">disabled</span>@endunless<br>
                                            <small class="text-muted">{{ '@'.$judge->username }} · {{ $judge->judged_events_count }} event(s)</small>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="ibox-footer text-right">
                        <button class="btn btn-white"><i class="fa fa-check"></i> Save selection</button>
                    </div>
                </div>
            </form>
        @endif
    </div>

    {{-- ---------------- Tabulator ---------------- --}}
    <div class="col-xl-5">
        <div class="ibox">
            <div class="ibox-title"><h5><i class="fa fa-desktop"></i> Tabulator</h5></div>
            <div class="ibox-content">
                <p class="text-muted">The tabulator runs this event on the day: opens and locks categories, watches the judges' scores live and prints the results. A tabulator account generated here can <b>only</b> see this event.</p>

                @forelse($event->tabulators as $tab)
                    <div class="d-flex align-items-center border-bottom p-xs m-b-xs" style="gap:8px">
                        <div class="tab-avatar" style="width:34px;height:34px;font-size:15px">T</div>
                        <div class="flex-grow-1">
                            <strong>{{ $tab->name }}</strong><br><code>{{ $tab->username }}</code>
                        </div>
                        <form method="POST" action="{{ route('admin.users.reset-password', $tab) }}" class="d-inline js-confirm"
                              data-confirm="Generate a new password for {{ $tab->username }}? The old password will stop working." data-type="info" data-color="#1ab394" data-button="Generate">
                            @csrf
                            <button class="btn btn-xs btn-white"><i class="fa fa-key"></i> New password</button>
                        </form>
                        <form method="POST" action="{{ route('admin.events.tabulators.destroy', [$event, $tab]) }}" class="d-inline js-confirm"
                              data-confirm="Delete the tabulator account {{ $tab->username }}?">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-white text-danger"><i class="fa fa-trash"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="m-b-sm"><i class="fa fa-info-circle text-warning"></i> No tabulator account for this event yet.</p>
                @endforelse

                <form method="POST" action="{{ route('admin.events.tabulators.store', $event) }}" class="m-t-md">
                    @csrf
                    <div class="form-group">
                        <label>Tabulator's name <small class="text-muted">(optional)</small></label>
                        <input name="name" class="form-control" placeholder="e.g. Head Tabulator" maxlength="255">
                    </div>
                    <button class="btn btn-primary"><i class="fa fa-magic"></i> Generate tabulator account</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
