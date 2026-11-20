@extends('layouts.app')

@section('title', $event->name)
@section('heading', $event->name)
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('judge.dashboard') }}">My Events</a></li>
    <li class="breadcrumb-item active"><strong>{{ $event->name }}</strong></li>
@endsection

@section('content')
@include('partials.event_banner', ['event' => $event])
@php($me = auth()->id())
@foreach($event->rounds as $round)
    <div class="ibox">
        <div class="ibox-title"><h5>{{ $round->name }} <small class="m-l-sm">{{ $round->contestants->count() }} contestant(s)</small></h5></div>
        <div class="ibox-content">
            <div class="row">
                @forelse($round->categories as $category)
                    @php($p = $progress[$category->id][$me] ?? ['entered' => 0, 'expected' => 0, 'submitted' => false])
                    <div class="col-md-6 col-xl-4">
                        <div class="widget p-md {{ $p['submitted'] ? 'navy-bg' : ($category->isOpen() ? 'white-bg border' : 'gray-bg') }}" style="{{ $category->isOpen() && ! $p['submitted'] ? 'border:2px solid #1ab394 !important' : '' }}">
                            <h3 class="m-b-xs">{{ $category->name }}</h3>
                            <small>{{ $category->criteria->count() }} criteria · {{ num($category->weight) }}% of round</small>
                            <div class="m-t-sm">
                                @if($p['submitted'])
                                    <i class="fa fa-check-circle"></i> Submitted
                                    <a href="{{ route('judge.sheet', $category) }}" class="btn btn-xs btn-white float-right">View</a>
                                @elseif($category->isOpen() && $event->status === 'ongoing')
                                    <span class="text-navy"><i class="fa fa-circle"></i> Open — {{ $p['entered'] }}/{{ $p['expected'] }} scored</span>
                                    <a href="{{ route('judge.sheet', $category) }}" class="btn btn-sm btn-primary btn-block m-t-sm"><i class="fa fa-pencil"></i> Score now</a>
                                @elseif($category->status === 'locked')
                                    <span class="text-danger"><i class="fa fa-lock"></i> Locked</span>
                                    @if($p['entered'])<a href="{{ route('judge.sheet', $category) }}" class="btn btn-xs btn-white float-right">View</a>@endif
                                @else
                                    <span class="text-muted"><i class="fa fa-clock-o"></i> Not yet open</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-muted">No categories in this round.</div>
                @endforelse
            </div>
        </div>
    </div>
@endforeach
@endsection

@push('scripts')
<script>setTimeout(function () { location.reload(); }, 10000);</script>
@endpush
