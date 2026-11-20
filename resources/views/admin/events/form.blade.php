@extends('layouts.app')

@php($editing = $event->exists)
@section('title', $editing ? 'Edit Event' : 'Add Event')
@section('heading', $editing ? 'Edit Event' : 'Add Event')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Events</a></li>
    @if($editing)<li class="breadcrumb-item"><a href="{{ route('admin.events.show', $event) }}">{{ $event->name }}</a></li>@endif
    <li class="breadcrumb-item active"><strong>{{ $editing ? 'Edit' : 'Add Event' }}</strong></li>
@endsection

@push('scripts')
<script>
    $('#banner-input').on('change', function () {
        var file = this.files && this.files[0];
        if (!file) { return; }
        $('#banner-preview').removeClass('is-empty').html('<img src="' + URL.createObjectURL(file) + '" alt="New banner preview">');
    });
</script>
@endpush

@section('content')
<form method="POST" action="{{ $editing ? route('admin.events.update', $event) : route('admin.events.store') }}" enctype="multipart/form-data">
    @csrf
    @if($editing) @method('PUT') @endif
    <div class="row">
        <div class="col-lg-8">
            <div class="ibox">
                <div class="ibox-title"><h5>Event Details</h5></div>
                <div class="ibox-content">
                    <div class="form-group">
                        <label>Event name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-lg" value="{{ old('name', $event->name) }}" placeholder="e.g. Mr. &amp; Ms. Foundation Day 2026" required autofocus>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Event type <span class="text-danger">*</span></label>
                            <select name="type" class="form-control" required>
                                @foreach(\App\Models\Event::TYPES as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type', $event->type) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Event date</label>
                            <input type="date" name="event_date" class="form-control" value="{{ old('event_date', optional($event->event_date)->format('Y-m-d')) }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Venue</label>
                        <input type="text" name="venue" class="form-control" value="{{ old('venue', $event->venue) }}" placeholder="e.g. University Gymnasium">
                    </div>
                    <div class="form-group m-b-none">
                        <label>Description</label>
                        <textarea name="description" rows="3" class="form-control" placeholder="Optional notes, theme or mechanics">{{ old('description', $event->description) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="ibox">
                <div class="ibox-title"><h5><i class="fa fa-image"></i> Event banner</h5></div>
                <div class="ibox-content">
                    <div class="event-banner-preview m-b-sm {{ $event->banner ? '' : 'is-empty' }}" id="banner-preview">
                        @if($event->banner)
                            <img src="{{ $event->bannerUrl() }}" alt="Current banner">
                        @else
                            <span class="text-muted"><i class="fa fa-image fa-2x d-block m-b-xs"></i>No banner yet</span>
                        @endif
                    </div>
                    <div class="form-group m-b-xs">
                        <label>{{ $event->banner ? 'Replace banner' : 'Upload a banner' }}</label>
                        <input type="file" name="banner" id="banner-input" class="form-control" accept=".png,.jpg,.jpeg,.webp,.gif">
                        <small class="text-muted">A wide image works best (e.g. 1920 × 480, the event tarpaulin or poster). Shown to judges on their event list, event page and score sheet, and on the event's pages. Max 8 MB.</small>
                    </div>
                    @if($event->banner)
                        <label class="m-0 text-danger"><input type="checkbox" name="remove_banner" value="1"> Remove the banner</label>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ibox">
                <div class="ibox-title"><h5>Scoring &amp; Status</h5></div>
                <div class="ibox-content">
                    <div class="form-group">
                        <label>Scoring method <span class="text-danger">*</span></label>
                        @foreach(\App\Models\Event::METHODS as $value => $label)
                            <div class="i-checks m-b-xs">
                                <label class="m-0">
                                    <input type="radio" name="scoring_method" value="{{ $value }}" @checked(old('scoring_method', $event->scoring_method) === $value)> {{ $label }}
                                </label>
                            </div>
                        @endforeach
                        <small class="text-muted d-block m-t-xs">
                            <b>Weighted average</b>: final score = average of all judges.<br>
                            <b>Rank sum</b>: each judge's scores become ranks; the lowest total rank wins.
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-control">
                            @foreach(\App\Models\Event::STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $event->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Judges can only score events whose tabulation is <b>Active</b>.</small>
                    </div>
                    <div class="form-group">
                        <label>Judge score-sheet layout</label>
                        <select name="sheet_layout" class="form-control">
                            <option value="">System default ({{ \App\Support\JudgeDisplay::LAYOUTS[\App\Support\JudgeDisplay::defaults()['sheet_layout']][0] }})</option>
                            @foreach(\App\Support\JudgeDisplay::LAYOUTS as $value => [$label])
                                <option value="{{ $value }}" @selected(old('sheet_layout', $event->sheet_layout) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">How judges see the contestants for this event. Other display options are in <a href="{{ route('admin.settings') }}">Settings</a>.</small>
                    </div>
                    @php($allLogos = \App\Models\Logo::ordered()->get())
                    <div class="form-group">
                        <label>Logos on judges' screens</label>
                        @if($allLogos->isEmpty())
                            <small class="text-muted d-block">No logos yet. <a href="{{ route('admin.settings') }}#logos">Add school, LGU or sponsor logos</a>.</small>
                        @else
                            @php($picked = collect(old('logos', $event->exists ? $event->logos->pluck('id')->all() : [])))
                            <div class="logo-picker">
                                @foreach($allLogos as $logo)
                                    <label class="logo-pick {{ $logo->show_on_all ? 'is-global' : '' }}" title="{{ $logo->show_on_all ? 'Shown on every event (change this in Logos)' : $logo->name }}">
                                        <input type="checkbox" name="logos[]" value="{{ $logo->id }}" @checked($logo->show_on_all || $picked->contains($logo->id)) @disabled($logo->show_on_all)>
                                        <img src="{{ $logo->url() }}" alt="">
                                        <small>{{ \Illuminate\Support\Str::limit($logo->name, 18) }}</small>
                                    </label>
                                @endforeach
                            </div>
                            <small class="text-muted">Greyed-out logos are set to show on every event. <a href="{{ route('admin.settings') }}#logos">Manage logos</a></small>
                        @endif
                    </div>
                    @unless($editing)
                        <div class="form-group">
                            <label>First round name</label>
                            <input type="text" name="first_round" class="form-control" value="{{ old('first_round', 'Preliminary Round') }}">
                            <small class="text-muted">You can add more rounds (e.g. Top 5, Finals) later.</small>
                        </div>
                    @endunless
                </div>
                <div class="ibox-footer">
                    <a href="{{ $editing ? route('admin.events.show', $event) : route('admin.events.index') }}" class="btn btn-white">Cancel</a>
                    <button class="btn btn-primary float-right" type="submit"><i class="fa fa-check"></i> {{ $editing ? 'Save Changes' : 'Create Event' }}</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
