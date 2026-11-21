<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->name }} — {{ $round->name }} Results</title>
    <link href="{{ asset('inspinia/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('inspinia/font-awesome/css/font-awesome.css') }}" rel="stylesheet">
    <link href="{{ asset('inspinia/css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('css/tabulation.css') }}" rel="stylesheet">
    <style>
        body { background: #fff; font-size: 12px; }
        .sheet { padding: 24px; }
        .sheet h1 { font-size: 22px; margin: 0; }
        table.table th, table.table td { padding: 4px 6px; }
        .signatures { margin-top: 48px; display: flex; flex-wrap: wrap; gap: 32px; }
        .signatures div { flex: 1 1 180px; text-align: center; border-top: 1px solid #333; padding-top: 4px; }
        .page-break { page-break-after: always; }
        .print-credit { margin-top: 28px; text-align: center; font-size: 10px; color: #555; }
        .print-credit img { height: 22px; vertical-align: middle; margin-right: 6px; }
        @page { size: landscape; margin: 12mm; }
    </style>
</head>
<body onload="window.print()">
@php($ranking = $event->scoring_method === 'ranking')
<div class="sheet">
    <div class="no-print m-b-md text-right">
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa fa-print"></i> Print</button>
        <button onclick="window.close()" class="btn btn-white btn-sm">Close</button>
    </div>

    @foreach($results['divisions'] as $division => $data)
        <div class="text-center m-b-md">
            @if(\App\Models\Setting::get('logos.on_print', '1') === '1')
                @include('partials.logo_band', ['logos' => $event->displayLogos(), 'class' => 'logo-band-print'])
            @endif
            @if($org = \App\Models\Setting::get('general.organization'))<div><b>{{ $org }}</b></div>@endif
            <h1>{{ $event->name }}</h1>
            <div>{{ $event->venue }}{{ $event->venue && $event->event_date ? ' · ' : '' }}{{ $event->event_date?->format('F d, Y') }}</div>
            <h3 class="m-t-sm m-b-none">{{ $round->name }} — {{ $division ? $division.' ' : '' }}Official Results</h3>
            <small>{{ \App\Models\Event::METHODS[$event->scoring_method] }} · Generated {{ now()->format('F d, Y h:i A') }}</small>
            @if($results['carry'])
                <div><small>Final score = {{ num(100 - $results['carry']['percent']) }}% {{ $round->name }} + {{ num($results['carry']['percent']) }}% {{ $results['carry']['from']->name }}</small></div>
            @endif
        </div>

        <table class="table table-bordered">
            <thead>
            <tr>
                <th class="text-center">Rank</th><th>Contestant</th>
                @if($ranking)
                    @foreach($results['judges'] as $judge)<th class="text-center">{{ $judge->name }}<br><small>Seat {{ $judge->pivot->seat ?? '—' }}</small></th>@endforeach
                    @if($results['carry'])<th class="text-center">{{ $results['carry']['from']->name }} ({{ num($results['carry']['percent']) }}%)</th>@endif
                    <th class="text-center">Rank Sum</th><th class="text-center">Avg</th>
                @else
                    @foreach($results['categories'] as $category)<th class="text-center">{{ $category->name }} ({{ num($category->weight) }}%)</th>@endforeach
                    @if($results['carry'])
                        <th class="text-center">This round ({{ num(100 - $results['carry']['percent']) }}%)</th>
                        <th class="text-center">{{ $results['carry']['from']->name }} ({{ num($results['carry']['percent']) }}%)</th>
                    @endif
                    <th class="text-center">Total</th>
                @endif
            </tr>
            </thead>
            <tbody>
            @foreach($data['overall'] as $row)
                <tr>
                    <td class="text-center"><b>{{ $row['rank'] ?? '—' }}</b></td>
                    <td><b>#{{ $row['contestant']->number }}</b> {{ $row['contestant']->name }} <small class="text-muted">{{ $row['contestant']->representing }}</small>@if(! empty($row['title']))<br><b class="placement-title-print">{{ $row['title'] }}</b>@endif</td>
                    @if($ranking)
                        @foreach($results['judges'] as $judge)
                            <td class="text-center">{{ num($row['judge_scores'][$judge->id] ?? null) }} ({{ num($row['judge_ranks'][$judge->id] ?? null, 1) }})</td>
                        @endforeach
                        @if($results['carry'])<td class="text-center">{{ num($row['carried']) }}</td>@endif
                        <td class="text-center"><b>{{ num($row['rank_sum'], 1) }}</b></td>
                        <td class="text-center">{{ num($row['average']) }}</td>
                    @else
                        @foreach($results['categories'] as $category)
                            <td class="text-center">{{ num($row['categories'][$category->id]['average']) }}</td>
                        @endforeach
                        @if($results['carry'])
                            <td class="text-center">{{ num($row['round_score']) }}</td>
                            <td class="text-center">{{ num($row['carried']) }}</td>
                        @endif
                        <td class="text-center"><b>{{ num($row['average']) }}</b></td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>

        @if($detailed)
            @foreach($results['categories'] as $category)
                <h4 class="m-t-md">{{ $category->name }} ({{ num($category->weight) }}%)</h4>
                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th class="text-center">Rank</th><th>Contestant</th>
                        @foreach($results['judges'] as $judge)<th class="text-center">{{ $judge->name }}<br><small>Seat {{ $judge->pivot->seat ?? '—' }}</small></th>@endforeach
                        <th class="text-center">Average</th>
                        @if($ranking)<th class="text-center">Rank Sum</th>@endif
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($data['categories'][$category->id] as $row)
                        <tr>
                            <td class="text-center">{{ $row['rank'] ?? '—' }}</td>
                            <td>#{{ $row['contestant']->number }} {{ $row['contestant']->name }}</td>
                            @foreach($results['judges'] as $judge)
                                <td class="text-center">{{ num($row['judge_scores'][$judge->id] ?? null) }}@if($ranking) ({{ num($row['judge_ranks'][$judge->id] ?? null, 1) }})@endif</td>
                            @endforeach
                            <td class="text-center"><b>{{ num($row['average']) }}</b></td>
                            @if($ranking)<td class="text-center">{{ num($row['rank_sum'], 1) }}</td>@endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endforeach
        @endif

        <div class="signatures">
            @foreach($results['judges'] as $judge)<div>{{ $judge->name }}<br><small>Judge · Seat {{ $judge->pivot->seat ?? '—' }}</small></div>@endforeach
            <div>{{ auth()->user()->name }}<br><small>Tabulator</small></div>
        </div>
        <div class="print-credit"><img src="{{ asset('images/sti-alaminos-small.png') }}" alt="">
            <b>{{ config('app.name') }}</b> · {{ config('app.full_name') }} &middot; &copy; {{ date('Y') }} {{ config('app.provider') }}. All rights reserved.</div>
        @unless($loop->last)<div class="page-break"></div>@endunless
    @endforeach
</div>
</body>
</html>
