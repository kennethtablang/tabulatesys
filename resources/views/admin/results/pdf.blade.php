<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $event->name }} · {{ $round->name }} Results</title>
<style>
    @page { margin: 14mm 12mm 16mm 12mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5px; color: #1b1b1b; }
    .head { text-align: center; margin-bottom: 8px; }
    .logos img { height: 52px; margin: 0 6px; }
    .org { font-weight: bold; font-size: 11px; }
    h1 { font-size: 17px; margin: 2px 0; }
    h2 { font-size: 12.5px; margin: 2px 0 1px; }
    h3 { font-size: 11px; margin: 12px 0 4px; }
    .meta { color: #555; font-size: 9px; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { border: 1px solid #b9bec4; padding: 3px 5px; }
    th { background: #2f4050; color: #fff; font-weight: bold; font-size: 9px; }
    td.c, th.c { text-align: center; }
    td.r { text-align: right; }
    tr:nth-child(even) td { background: #f4f6f7; }
    .rank { font-weight: bold; }
    .total { font-weight: bold; }
    .sign { width: 100%; margin-top: 34px; border: 0; }
    .sign td { border: 0; text-align: center; padding: 0 10px; vertical-align: bottom; }
    .sign .line { border-top: 1px solid #333; padding-top: 3px; }
    .sign small { color: #555; }
    .page-break { page-break-after: always; }
    .keep { page-break-inside: avoid; }
    .footer { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: center; font-size: 8px; color: #555; }
    .footer img { height: 14px; vertical-align: middle; margin-right: 4px; }
    .footer .pageno:after { content: counter(page); }
</style>
</head>
<body>
@php($ranking = $event->scoring_method === 'ranking')
@php($carry = $results['carry'])
<div class="footer">
    @if(is_file($creditLogo))<img src="{{ $creditLogo }}" alt="">@endif
    @if(is_file(public_path('images/prism-icon.svg')))<img src="{{ public_path('images/prism-icon.svg') }}" alt="">@endif
    <b>{{ config('app.name') }}</b> · {{ config('app.full_name') }} &middot; &copy; {{ date('Y') }} {{ $credit }}. All rights reserved. &middot; Page <span class="pageno"></span>
</div>

@foreach($results['divisions'] as $division => $data)
    <div class="head">
        @if($logos->isNotEmpty())
            <div class="logos">@foreach($logos as $path)<img src="{{ $path }}" alt="">@endforeach</div>
        @endif
        @if($organization)<div class="org">{{ $organization }}</div>@endif
        <h1>{{ $event->name }}</h1>
        <h2>{{ $round->name }} — {{ $division ? $division.' ' : '' }}Official Results</h2>
        <div class="meta">
            {{ trim(($event->venue ?? '').($event->event_date ? ' · '.$event->event_date->format('F d, Y') : ''), ' ·') }}
            · {{ \App\Models\Event::METHODS[$event->scoring_method] }}
            @if($carry) · Final = {{ num(100 - $carry['percent']) }}% {{ $round->name }} + {{ num($carry['percent']) }}% {{ $carry['from']->name }}@endif
            · Generated {{ now()->format('F d, Y h:i A') }}
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th class="c">Rank</th><th>Contestant</th>
            @if($ranking)
                @foreach($results['judges'] as $judge)<th class="c">{{ $judge->name }}<br>score (rank)</th>@endforeach
                @if($carry)<th class="c">{{ $carry['from']->name }}<br>({{ num($carry['percent']) }}%)</th>@endif
                <th class="c">Rank sum</th><th class="c">Average</th>
            @else
                @foreach($results['categories'] as $category)<th class="c">{{ $category->name }}<br>({{ num($category->weight) }}%)</th>@endforeach
                @if($carry)<th class="c">This round<br>({{ num(100 - $carry['percent']) }}%)</th><th class="c">{{ $carry['from']->name }}<br>({{ num($carry['percent']) }}%)</th>@endif
                <th class="c">Total</th>
            @endif
        </tr>
        </thead>
        <tbody>
        @foreach($data['overall'] as $row)
            <tr>
                <td class="c rank">{{ $row['rank'] ?? '—' }}</td>
                <td><b>#{{ $row['contestant']->number }}</b> {{ $row['contestant']->name }}@if($row['contestant']->representing)<br><span class="meta">{{ $row['contestant']->representing }}</span>@endif</td>
                @if($ranking)
                    @foreach($results['judges'] as $judge)
                        <td class="c">{{ num($row['judge_scores'][$judge->id] ?? null) }} ({{ num($row['judge_ranks'][$judge->id] ?? null, 1) }})</td>
                    @endforeach
                    @if($carry)<td class="c">{{ num($row['carried']) }}</td>@endif
                    <td class="c total">{{ num($row['rank_sum'], 1) }}</td>
                    <td class="c">{{ num($row['average']) }}</td>
                @else
                    @foreach($results['categories'] as $category)
                        <td class="c">{{ num($row['categories'][$category->id]['average']) }}</td>
                    @endforeach
                    @if($carry)<td class="c">{{ num($row['round_score']) }}</td><td class="c">{{ num($row['carried']) }}</td>@endif
                    <td class="c total">{{ num($row['average']) }}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>

    @if($detailed)
        @foreach($results['categories'] as $category)
            <div class="keep">
            <h3>{{ $category->name }} ({{ num($category->weight) }}%)</h3>
            <table>
                <thead>
                <tr>
                    <th class="c">Rank</th><th>Contestant</th>
                    @foreach($results['judges'] as $judge)<th class="c">{{ $judge->name }}</th>@endforeach
                    <th class="c">Average</th>
                    @if($ranking)<th class="c">Rank sum</th>@endif
                </tr>
                </thead>
                <tbody>
                @foreach($data['categories'][$category->id] as $row)
                    <tr>
                        <td class="c rank">{{ $row['rank'] ?? '—' }}</td>
                        <td>#{{ $row['contestant']->number }} {{ $row['contestant']->name }}</td>
                        @foreach($results['judges'] as $judge)
                            <td class="c">{{ num($row['judge_scores'][$judge->id] ?? null) }}@if($ranking) ({{ num($row['judge_ranks'][$judge->id] ?? null, 1) }})@endif</td>
                        @endforeach
                        <td class="c total">{{ num($row['average']) }}</td>
                        @if($ranking)<td class="c">{{ num($row['rank_sum'], 1) }}</td>@endif
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        @endforeach
    @endif

    <table class="sign">
        <tr>
            @foreach($results['judges'] as $judge)
                <td><div class="line">{{ $judge->name }}<br><small>Judge</small></div></td>
            @endforeach
            <td><div class="line">{{ $tabulator }}<br><small>Tabulator</small></div></td>
        </tr>
    </table>
    @unless($loop->last)<div class="page-break"></div>@endunless
@endforeach
</body>
</html>
