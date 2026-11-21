@extends('layouts.app')

@section('title', 'Placement Titles · '.$round->name)
@section('heading', 'Placement Titles')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Events</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.events.setup', $event) }}">{{ $event->name }}</a></li>
    <li class="breadcrumb-item active"><strong>{{ $round->name }}</strong></li>
@endsection

@section('content')
@include('admin.events._nav')

<form method="POST" action="{{ route('admin.rounds.titles.update', $round) }}" id="titles-form">
    @csrf @method('PUT')
    <div class="ibox">
        <div class="ibox-title">
            <h5><i class="fa fa-trophy"></i> Titles for the top finishers of {{ $round->name }}</h5>
        </div>
        <div class="ibox-content">
            <p class="text-muted">Give each place a title, e.g. <b>1 = Ms. Foundation Day 2026</b>, <b>2 = 1st Runner-Up</b>. Leave a place empty if it has no title.
                @if($divisions) A division's own title (e.g. <i>Mr. Foundation Day</i>) replaces the "All divisions" title for that division. @endif
                Contestants tied on a place share its title.</p>

            <div class="d-flex flex-wrap align-items-center m-b-sm" style="gap:6px">
                <span class="text-muted">Quick fill:</span>
                @foreach(\App\Models\RoundTitle::presets() as $key => $list)
                    <button type="button" class="btn btn-xs btn-white js-preset" data-titles='@json($list)'>{{ implode(', ', array_slice($list, 0, 3)) }}{{ count($list) > 3 ? '…' : '' }}</button>
                @endforeach
                <button type="button" class="btn btn-xs btn-white text-danger js-clear">Clear all</button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered m-b-sm" id="titles-table">
                    <thead class="thead-light">
                    <tr>
                        <th style="width:90px" class="text-center">Place</th>
                        <th>All divisions</th>
                        @foreach($divisions as $division)<th>{{ $division }} only <small class="text-muted font-weight-normal">(optional)</small></th>@endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @for($rank = 1; $rank <= $places; $rank++)
                        <tr data-rank="{{ $rank }}">
                            <td class="text-center"><span class="rank-badge rank-{{ $rank }}">{{ $rank }}</span></td>
                            <td><input name="titles[{{ $rank }}][_all]" class="form-control js-all" maxlength="120" value="{{ optional($round->titles->first(fn ($t) => $t->rank === $rank && $t->division === null))->title }}" placeholder="{{ $rank === 1 ? 'e.g. Winner' : 'e.g. Runner-Up' }}"></td>
                            @foreach($divisions as $division)
                                <td><input name="titles[{{ $rank }}][{{ $division }}]" class="form-control" maxlength="120" value="{{ optional($round->titles->first(fn ($t) => $t->rank === $rank && $t->division === $division))->title }}" placeholder="{{ $rank === 1 ? 'e.g. '.$division.'. Foundation Day' : 'same as All divisions' }}"></td>
                            @endforeach
                        </tr>
                    @endfor
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-xs btn-white" id="add-place"><i class="fa fa-plus"></i> Add a place</button>
        </div>
        <div class="ibox-footer d-flex">
            <a href="{{ route('admin.events.setup', $event) }}" class="btn btn-white">Back</a>
            <button class="btn btn-primary ml-auto"><i class="fa fa-check"></i> Save Titles</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function ($) {
    var divisions = @json($divisions);
    $('.js-preset').on('click', function () {
        var list = $(this).data('titles');
        while ($('#titles-table tbody tr').length < list.length) { $('#add-place').trigger('click'); }
        $('#titles-table .js-all').each(function (i) { this.value = list[i] || ''; });
    });
    $('.js-clear').on('click', function () { $('#titles-table input').val(''); });
    $('#add-place').on('click', function () {
        var rank = $('#titles-table tbody tr').length + 1;
        var esc = function (s) { return $('<div>').text(s).html(); };
        var row = '<tr data-rank="' + rank + '"><td class="text-center"><span class="rank-badge">' + rank + '</span></td>' +
            '<td><input name="titles[' + rank + '][_all]" class="form-control js-all" maxlength="120"></td>' +
            divisions.map(function (d) { return '<td><input name="titles[' + rank + '][' + esc(d) + ']" class="form-control" maxlength="120"></td>'; }).join('') + '</tr>';
        $('#titles-table tbody').append(row);
    });
})(jQuery);
</script>
@endpush
