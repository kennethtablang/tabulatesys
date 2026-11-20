@extends('layouts.app')

@section('title', $event->name)
@section('heading', $event->name)
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Events</a></li>
    <li class="breadcrumb-item active"><strong>{{ $event->name }}</strong></li>
@endsection
@section('actions')
    <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-white"><i class="fa fa-pencil"></i> Edit</a>
    <form method="POST" action="{{ route('admin.events.destroy', $event) }}" class="d-inline js-confirm"
          data-confirm="Delete {{ $event->name }} together with all its contestants, criteria and scores?">
        @csrf @method('DELETE')
        <button class="btn btn-danger"><i class="fa fa-trash"></i></button>
    </form>
@endsection

@section('content')
@include('partials.event_banner', ['event' => $event])
@include('admin.events._nav')

@php($categories = $event->rounds->flatMap->categories)
@php($checklist = [
    ['At least one category with criteria', $categories->isNotEmpty() && $categories->every(fn ($c) => $c->criteria->isNotEmpty()), route('admin.events.setup', $event)],
    ['Criteria weights total 100% per category', $categories->isNotEmpty() && $categories->every(fn ($c) => abs($c->criteriaWeight() - 100) < 0.01), route('admin.events.setup', $event)],
    ['Category weights total 100% per round', $event->rounds->every(fn ($r) => $r->categories->isNotEmpty() && abs($r->totalWeight() - 100) < 0.01), route('admin.events.setup', $event)],
    ['Contestants registered', $event->contestants_count > 0, route('admin.contestants.index', $event)],
    ['Judges assigned', $event->judges->isNotEmpty(), route('admin.events.judges', $event)],
    ['Tabulator account created', $event->tabulators()->exists(), route('admin.events.judges', $event)],
    ['Tabulation set to Active', $event->status !== 'draft', null],
])

<div class="row">
    <div class="col-lg-4">
        <div class="ibox">
            <div class="ibox-title"><h5>Event Information</h5></div>
            <div class="ibox-content">
                <dl class="row m-b-none">
                    <dt class="col-5">Type</dt><dd class="col-7">{{ $event->typeLabel() }}</dd>
                    <dt class="col-5">Date</dt><dd class="col-7">{{ $event->event_date?->format('F d, Y') ?? '—' }}</dd>
                    <dt class="col-5">Venue</dt><dd class="col-7">{{ $event->venue ?: '—' }}</dd>
                    <dt class="col-5">Scoring</dt><dd class="col-7">{{ \App\Models\Event::METHODS[$event->scoring_method] }}</dd>
                    <dt class="col-5">Contestants</dt><dd class="col-7">{{ $event->contestants_count }}</dd>
                    <dt class="col-5">Judges</dt><dd class="col-7">{{ $event->judges->pluck('name')->join(', ') ?: '—' }}</dd>
                </dl>
                @if($event->description)
                    <hr><p class="m-b-none">{{ $event->description }}</p>
                @endif
            </div>
        </div>

        <div class="ibox">
            <div class="ibox-title"><h5>Readiness Checklist</h5></div>
            <div class="ibox-content no-padding">
                <ul class="list-group">
                    @foreach($checklist as [$label, $ok, $link])
                        <li class="list-group-item">
                            <i class="fa {{ $ok ? 'fa-check-circle text-navy' : 'fa-circle-o text-muted' }}"></i>
                            @if($link && ! $ok)<a href="{{ $link }}">{{ $label }}</a>@else {{ $label }} @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        @foreach($event->rounds as $round)
            <div class="ibox">
                <div class="ibox-title">
                    <h5>{{ $round->name }} <small class="m-l-sm">{{ $round->contestants->count() }} contestant(s)</small></h5>
                    <div class="ibox-tools">
                        <a href="{{ route('admin.events.results', [$event, 'round' => $round->id]) }}" class="btn btn-xs btn-white"><i class="fa fa-bar-chart"></i> Results</a>
                    </div>
                </div>
                <div class="ibox-content table-responsive">
                    @if($round->categories->isEmpty())
                        <p class="text-muted m-b-none">No categories yet. <a href="{{ route('admin.events.setup', $event) }}">Add categories</a>.</p>
                    @else
                        <table class="table table-sm m-b-none">
                            <thead>
                            <tr><th>Category</th><th class="text-center">Weight</th><th class="text-center">Status</th><th style="width:40%">Judges submitted</th><th></th></tr>
                            </thead>
                            <tbody>
                            @foreach($round->categories as $category)
                                @php($p = collect($progress[$category->id] ?? []))
                                @php($done = $p->where('submitted', true)->count())
                                @php($pct = $p->count() ? round($done / $p->count() * 100) : 0)
                                <tr>
                                    <td><strong>{{ $category->name }}</strong></td>
                                    <td class="text-center">{{ rtrim(rtrim(number_format($category->weight, 2), '0'), '.') }}%</td>
                                    <td class="text-center"><span class="badge {{ $category->statusBadge() }}">{{ ucfirst($category->status) }}</span></td>
                                    <td>
                                        <small>{{ $done }} / {{ $p->count() }}</small>
                                        <div class="progress progress-mini"><div class="progress-bar" style="width: {{ $pct }}%"></div></div>
                                    </td>
                                    <td class="text-right">
                                        @include('admin.events._category_status', ['category' => $category])
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
