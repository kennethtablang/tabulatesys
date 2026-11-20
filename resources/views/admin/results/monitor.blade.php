@extends('layouts.app')

@section('title', 'Monitor · '.$event->name)
@section('heading', 'Scoring Monitor')
@section('breadcrumbs')
    @include('admin.results._breadcrumbs')
    <li class="breadcrumb-item active"><strong>Monitor</strong></li>
@endsection
@section('actions')
    @php($eventLocked = \App\Support\ScreenLock::eventLocked($event))
    <form method="POST" action="{{ route('admin.judges.lock') }}" class="d-inline js-confirm"
          data-confirm="{{ $eventLocked ? 'Unlock the screens of every judge of this event?' : 'Lock the screens of every judge of this event? They cannot enter or change scores until you unlock.' }}"
          data-type="warning" data-color="{{ $eventLocked ? '#1ab394' : '#cb4956' }}" data-button="{{ $eventLocked ? 'Unlock all' : 'Lock all' }}">
        @csrf <input type="hidden" name="scope" value="event"><input type="hidden" name="event" value="{{ $event->id }}">
        <input type="hidden" name="lock" value="{{ $eventLocked ? 0 : 1 }}">
        <button class="btn {{ $eventLocked ? 'btn-warning' : 'btn-white' }}"><i class="fa {{ $eventLocked ? 'fa-unlock' : 'fa-lock' }}"></i> {{ $eventLocked ? 'Unlock all judge screens' : 'Lock all judge screens' }}</button>
    </form>
    <form method="POST" action="{{ route('admin.judges.refresh') }}" class="d-inline js-confirm"
          data-confirm="Reload the screens of this event's judges now? Unsaved scores are saved first." data-type="info" data-color="#1ab394" data-button="Refresh now">
        @csrf <input type="hidden" name="event" value="{{ $event->id }}">
        <button class="btn btn-white"><i class="fa fa-refresh"></i> Refresh judges' screens</button>
    </form>
@endsection

@section('content')
@include('admin.events._nav')

<div class="d-flex m-b-md">
    <p class="text-muted m-0">Track each judge's progress live. Open a category to let judges score, and lock it once everyone has submitted.</p>
    <label class="ml-auto m-0"><input type="checkbox" id="auto-refresh" checked> Live updates <span class="live-dot" title="Updates as judges type"></span></label>
</div>

<div data-refresh-url="{{ route('admin.events.monitor', $event) }}" data-pulse-url="{{ route('admin.events.pulse', $event) }}"
     data-pulse-version="{{ $version }}" data-refresh-toggle="#auto-refresh">
    @include('admin.results._monitor')
</div>
@endsection
