@extends('layouts.app')

@section('title', $round->name.' Contestants')
@section('heading', $round->name.' — Contestants')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Events</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.events.setup', $event) }}">{{ $event->name }}</a></li>
    <li class="breadcrumb-item active"><strong>{{ $round->name }}</strong></li>
@endsection

@section('content')
@include('admin.events._nav')

<div class="row">
    @if($previous)
        <div class="col-lg-4">
            <div class="ibox">
                <div class="ibox-title"><h5>Advance finalists from {{ $previous->name }}</h5></div>
                <div class="ibox-content">
                    <p>Automatically select the top contestants (per division) based on the current results of <b>{{ $previous->name }}</b>. Ties at the cut-off are included.</p>
                    <form method="POST" action="{{ route('admin.rounds.advance', $round) }}" class="js-confirm"
                          data-confirm="This replaces the current contestant list of {{ $round->name }}." data-type="info" data-color="#1ab394">
                        @csrf
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">Top</span></div>
                            <input type="number" name="top" min="1" value="5" class="form-control">
                            <div class="input-group-append"><button class="btn btn-primary"><i class="fa fa-level-up"></i> Advance</button></div>
                        </div>
                    </form>
                    @if($previousResults)
                        @foreach($previousResults['divisions'] as $division => $data)
                            <h5 class="m-t-md">{{ $division ?: 'Standings' }}</h5>
                            <table class="table table-sm">
                                @foreach(array_slice($data['overall'], 0, 10, true) as $row)
                                    <tr>
                                        <td style="width:40px"><span class="rank-badge rank-{{ $row['rank'] }}">{{ $row['rank'] ?? '—' }}</span></td>
                                        <td>{{ $row['contestant']->label() }}</td>
                                        <td class="text-right">{{ num($event->scoring_method === 'ranking' ? $row['rank_sum'] : $row['average']) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="{{ $previous ? 'col-lg-8' : 'col-lg-12' }}">
        <form method="POST" action="{{ route('admin.rounds.participants.save', $round) }}">
            @csrf @method('PUT')
            <div class="ibox">
                <div class="ibox-title">
                    <h5>Select contestants competing in {{ $round->name }}</h5>
                    <div class="ibox-tools">
                        <button type="button" class="btn btn-xs btn-white" onclick="$('.check-tile input').prop('checked', true).trigger('change')">Select all</button>
                        <button type="button" class="btn btn-xs btn-white" onclick="$('.check-tile input').prop('checked', false).trigger('change')">Clear</button>
                    </div>
                </div>
                <div class="ibox-content">
                    @php($selected = $round->contestants->pluck('id')->all())
                    @forelse($contestants->groupBy(fn ($c) => $c->division ?? '') as $division => $group)
                        @if($division)<h4 class="m-t-sm">{{ $division }}</h4>@endif
                        <div class="row">
                            @foreach($group as $c)
                                <div class="col-md-6 col-xl-4">
                                    <label class="check-tile">
                                        <input type="checkbox" name="contestants[]" value="{{ $c->id }}" @checked(in_array($c->id, $selected))>
                                        <img src="{{ $c->photoUrl() }}" class="contestant-thumb" alt="">
                                        <span><b>#{{ $c->number }}</b> {{ $c->name }}<br><small class="text-muted">{{ $c->representing }}</small></span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <p class="text-muted">No contestants registered. <a href="{{ route('admin.contestants.index', $event) }}">Add contestants</a>.</p>
                    @endforelse
                </div>
                <div class="ibox-footer text-right">
                    <a href="{{ route('admin.events.setup', $event) }}" class="btn btn-white">Back</a>
                    <button class="btn btn-primary"><i class="fa fa-check"></i> Save Contestants</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
