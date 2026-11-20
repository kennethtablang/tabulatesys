@extends('layouts.app')

@section('title', 'Contestants · '.$event->name)
@section('heading', 'Contestants')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Events</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.events.show', $event) }}">{{ $event->name }}</a></li>
    <li class="breadcrumb-item active"><strong>Contestants</strong></li>
@endsection
@section('actions')
    <button class="btn btn-primary" data-toggle="modal" data-target="#contestantModal"
            data-action="{{ route('admin.contestants.store', $event) }}" data-title="Add Contestant"
            data-values="{{ json_encode(['number' => ($contestants->max('number') ?? 0) + 1]) }}">
        <i class="fa fa-plus"></i> Add Contestant
    </button>
@endsection

@section('content')
@include('admin.events._nav')

<div class="ibox">
    <div class="ibox-title">
        <h5>{{ $contestants->count() }} contestant(s)</h5>
        @if($divisions->isNotEmpty())
            <div class="ibox-tools"><small class="text-muted">Divisions: {{ $divisions->join(', ') }} — rankings are computed per division.</small></div>
        @endif
    </div>
    <div class="ibox-content table-responsive">
        <table class="table table-hover js-datatable">
            <thead>
            <tr><th style="width:60px">No.</th><th>Contestant</th><th>Division</th><th>Representing</th><th>Rounds</th><th style="width:90px"></th></tr>
            </thead>
            <tbody>
            @foreach($contestants as $c)
                <tr>
                    <td><span class="badge badge-primary" style="font-size:14px">{{ $c->number }}</span></td>
                    <td>
                        <img src="{{ $c->photoUrl() }}" class="contestant-thumb m-r-sm" alt="">
                        <strong>{{ $c->name }}</strong>
                    </td>
                    <td>{{ $c->division ?: '—' }}</td>
                    <td>{{ $c->representing ?: '—' }}</td>
                    <td>@foreach($c->rounds as $r)<span class="badge badge-light">{{ $r->name }}</span> @endforeach</td>
                    <td class="text-right">
                        <button class="btn btn-xs btn-white" data-toggle="modal" data-target="#contestantModal"
                                data-action="{{ route('admin.contestants.update', $c) }}" data-method="PUT" data-title="Edit Contestant"
                                data-values="{{ json_encode($c->only('number', 'name', 'division', 'representing', 'details')) }}">
                            <i class="fa fa-pencil"></i>
                        </button>
                        <form method="POST" action="{{ route('admin.contestants.destroy', $c) }}" class="d-inline js-confirm"
                              data-confirm="Remove {{ $c->name }} and all of their scores?">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-white text-danger"><i class="fa fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('modals')
    <div class="modal inmodal fade js-form-modal" id="contestantModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" enctype="multipart/form-data">@csrf
                <div class="modal-header"><h4 class="modal-title" data-default="Contestant">Contestant</h4></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-4 form-group"><label>No.</label><input name="number" type="number" min="1" class="form-control" required></div>
                        <div class="col-8 form-group"><label>Full name</label><input name="name" class="form-control" required></div>
                    </div>
                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Division <small class="text-muted">(optional)</small></label>
                            <input name="division" class="form-control" list="division-list" placeholder="e.g. Mr / Ms">
                            <datalist id="division-list">@foreach($divisions as $d)<option value="{{ $d }}">@endforeach</datalist>
                        </div>
                        <div class="col-6 form-group"><label>Representing</label><input name="representing" class="form-control" placeholder="School, barangay, team…"></div>
                    </div>
                    <div class="form-group"><label>Details</label><textarea name="details" rows="2" class="form-control" placeholder="Age, course, talent, song title…"></textarea></div>
                    <div class="form-group m-b-none"><label>Photo</label><input name="photo" type="file" accept="image/*" class="form-control-file"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-white" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div></div>
    </div>
@endpush
