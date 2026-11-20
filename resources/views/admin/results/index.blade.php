@extends('layouts.app')

@section('title', 'Results · '.$event->name)
@section('heading', 'Live Results')
@section('breadcrumbs')
    @include('admin.results._breadcrumbs')
    <li class="breadcrumb-item active"><strong>Results</strong></li>
@endsection
@section('actions')
    @if($round)
        <div class="btn-group">
            <a href="{{ route('admin.rounds.print', $round) }}" target="_blank" class="btn btn-white"><i class="fa fa-print"></i> Print</a>
            <button type="button" class="btn btn-white dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="More print and export options"></button>
            <div class="dropdown-menu dropdown-menu-right">
                <h6 class="dropdown-header">Print</h6>
                <a class="dropdown-item" href="{{ route('admin.rounds.print', $round) }}" target="_blank"><i class="fa fa-print fa-fw"></i> Summary</a>
                <a class="dropdown-item" href="{{ route('admin.rounds.print', [$round, 'detailed' => 1]) }}" target="_blank"><i class="fa fa-print fa-fw"></i> Detailed (every judge, every category)</a>
                <div class="dropdown-divider"></div>
                <h6 class="dropdown-header">Download</h6>
                <a class="dropdown-item" href="{{ route('admin.rounds.export.pdf', $round) }}"><i class="fa fa-file-pdf-o fa-fw"></i> PDF · summary</a>
                <a class="dropdown-item" href="{{ route('admin.rounds.export.pdf', [$round, 'detailed' => 1]) }}"><i class="fa fa-file-pdf-o fa-fw"></i> PDF · detailed</a>
                <a class="dropdown-item" href="{{ route('admin.rounds.export.excel', $round) }}"><i class="fa fa-file-excel-o fa-fw"></i> Excel (.xlsx) · all sheets</a>
            </div>
        </div>
        <a href="{{ route('admin.rounds.export.excel', $round) }}" class="btn btn-white" title="Download Excel"><i class="fa fa-file-excel-o"></i> Excel</a>
        <a href="{{ route('admin.rounds.export.pdf', $round) }}" class="btn btn-white" title="Download PDF"><i class="fa fa-file-pdf-o"></i> PDF</a>
    @endif
@endsection

@section('content')
@include('admin.events._nav')

<div class="d-flex flex-wrap align-items-center m-b-md" style="gap:8px">
    <div class="btn-group">
        @foreach($event->rounds as $r)
            <a href="{{ route('admin.events.results', [$event, 'round' => $r->id, 'view' => $chart]) }}" class="btn btn-sm js-round-link {{ $round && $round->id === $r->id ? 'btn-primary' : 'btn-white' }}">{{ $r->name }}</a>
        @endforeach
    </div>
    <span class="badge badge-light">{{ \App\Models\Event::METHODS[$event->scoring_method] }}</span>
    <label class="ml-auto m-0"><input type="checkbox" id="auto-refresh" checked> Live updates <span class="live-dot" title="Updates as judges type"></span></label>
</div>

<div class="btn-group flex-wrap m-b-md" role="group" aria-label="Results view" id="results-views">
    @foreach([
        'table' => ['Tables', 'fa-table'],
        'standings' => ['Standings', 'fa-bar-chart'],
        'breakdown' => ['Breakdown', 'fa-align-left'],
        'judges' => ['Judges', 'fa-th'],
        'categories' => ['By Category', 'fa-columns'],
        'podium' => ['Top 3', 'fa-trophy'],
    ] as $key => [$label, $icon])
        <button type="button" class="btn btn-sm {{ $chart === $key ? 'btn-primary' : 'btn-white' }}" data-view="{{ $key }}"><i class="fa {{ $icon }}"></i> {{ $label }}</button>
    @endforeach
</div>

<div id="results-panel"
     data-refresh-url="{{ $round ? route('admin.events.results', [$event, 'round' => $round->id, 'view' => $chart]) : '' }}"
     data-pulse-url="{{ route('admin.events.pulse', $event) }}" data-pulse-version="{{ $version }}" data-refresh-toggle="#auto-refresh">
    @include($chart === 'table' ? 'admin.results._tables' : 'admin.results._charts')
</div>
@endsection

@push('scripts')
<script>
(function ($) {
    var $panel = $('#results-panel');
    var KEY = 'tms-results-view';

    function setView(view, fetchNow) {
        $('#results-views [data-view]').each(function () {
            $(this).toggleClass('btn-primary', this.dataset.view === view).toggleClass('btn-white', this.dataset.view !== view);
        });
        try { localStorage.setItem(KEY, view); } catch (e) {}
        var url = $panel.attr('data-refresh-url');
        if (!url) { return; }
        var u = new URL(url, location.href);
        u.searchParams.set('view', view);
        $panel.attr('data-refresh-url', u.toString()).data('refresh-url', u.toString());
        var page = new URL(location.href);
        page.searchParams.set('view', view);
        history.replaceState(null, '', page.toString());
        $('.js-round-link').each(function () {
            var l = new URL(this.href); l.searchParams.set('view', view); this.href = l.toString();
        });
        if (fetchNow) {
            $panel.css('opacity', .5);
            $.ajax({url: u.toString(), cache: false}).done(function (html) { $panel.html(html); }).always(function () { $panel.css('opacity', 1); });
        }
    }

    $('#results-views').on('click', '[data-view]', function () { setView(this.dataset.view, true); });

    // Reopen the view this viewer used last (unless the link already names one).
    if (!new URL(location.href).searchParams.has('view')) {
        var saved = null;
        try { saved = localStorage.getItem(KEY); } catch (e) {}
        if (saved && saved !== @json($chart) && $('#results-views [data-view="' + saved + '"]').length) { setView(saved, true); }
    }
})(jQuery);
</script>
@endpush
