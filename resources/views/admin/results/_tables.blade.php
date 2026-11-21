@php($ranking = $event->scoring_method === 'ranking')
@if(! $results || empty($results['divisions']))
    <div class="ibox"><div class="ibox-content text-center text-muted p-xl">
        No contestants in this round yet.
    </div></div>
@else
    @php($carry = $results['carry'] ?? null)
    <div class="alert {{ $carry ? 'alert-info' : 'alert-secondary' }} p-xs m-b-sm">
        @if($carry)
            <i class="fa fa-link"></i> <b>{{ $round->name }}</b> final score = <b>{{ num(100 - $carry['percent']) }}%</b> this round + <b>{{ num($carry['percent']) }}%</b> of <b>{{ $carry['from']->name }}</b>.
        @else
            <i class="fa fa-refresh"></i> Scores start from zero in <b>{{ $round->name }}</b>. Earlier rounds have no bearing.
        @endif
    </div>
    @foreach($results['divisions'] as $division => $data)
        <div class="ibox">
            <div class="ibox-title">
                <h5><i class="fa fa-trophy text-warning"></i> {{ $division ? $division.' — ' : '' }}Overall Standing · {{ $round->name }}</h5>
                <div class="ibox-tools"><small class="text-muted">Updated {{ now()->format('h:i:s A') }}</small></div>
            </div>
            <div class="ibox-content table-responsive">
                <table class="table table-striped table-hover results-table m-b-none">
                    <thead>
                    <tr>
                        <th class="text-center" style="width:60px">Rank</th>
                        <th>Contestant</th>
                        @if($ranking)
                            @foreach($results['judges'] as $judge)
                                <th class="text-center"><small class="text-muted d-block">Seat {{ $judge->pivot->seat ?? '—' }}</small>{{ $judge->name }}<br><small class="text-muted">score (rank)</small></th>
                            @endforeach
                            @if($carry)<th class="text-center">{{ $carry['from']->name }}<br><small class="text-muted">avg · {{ num($carry['percent']) }}% bearing</small></th>@endif
                            <th class="text-center total-col">Rank Sum</th>
                            <th class="text-center">Avg Score</th>
                        @else
                            @foreach($results['categories'] as $category)
                                <th class="text-center">{{ $category->name }}<br><small class="text-muted">{{ num($category->weight) }}%</small></th>
                            @endforeach
                            @if($carry)
                                <th class="text-center">This round<br><small class="text-muted">{{ num(100 - $carry['percent']) }}%</small></th>
                                <th class="text-center">{{ $carry['from']->name }}<br><small class="text-muted">{{ num($carry['percent']) }}%</small></th>
                            @endif
                            <th class="text-center total-col">Total</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($data['overall'] as $row)
                        <tr>
                            <td class="text-center"><span class="rank-badge rank-{{ $row['rank'] }}">{{ $row['rank'] ?? '—' }}</span></td>
                            <td>
                                <img src="{{ $row['contestant']->photoUrl() }}" class="contestant-thumb m-r-xs" alt="">
                                <b>#{{ $row['contestant']->number }}</b> {{ $row['contestant']->name }}
                                @if(! empty($row['title']))<span class="placement-title m-l-xs"><i class="fa fa-trophy"></i> {{ $row['title'] }}</span>@endif
                                @if($row['contestant']->representing)<br><small class="text-muted" style="margin-left:48px">{{ $row['contestant']->representing }}</small>@endif
                            </td>
                            @if($ranking)
                                @foreach($results['judges'] as $judge)
                                    <td class="text-center">
                                        {{ num($row['judge_scores'][$judge->id] ?? null) }}
                                        <span class="judge-rank">({{ num($row['judge_ranks'][$judge->id] ?? null, 1) }})</span>
                                    </td>
                                @endforeach
                                @if($carry)<td class="text-center">{{ num($row['carried']) }}</td>@endif
                                <td class="text-center total-col">{{ num($row['rank_sum'], 1) }}</td>
                                <td class="text-center">{{ num($row['average']) }}</td>
                            @else
                                @foreach($results['categories'] as $category)
                                    @php($cat = $row['categories'][$category->id])
                                    <td class="text-center">
                                        {{ num($cat['average']) }}
                                        @if($cat['weighted'] !== null)<br><small class="text-muted">{{ num($cat['weighted']) }} pts</small>@endif
                                    </td>
                                @endforeach
                                @if($carry)
                                    <td class="text-center">{{ num($row['round_score']) }}</td>
                                    <td class="text-center">{{ num($row['carried']) }}@if($row['carried_points'] !== null)<br><small class="text-muted">{{ num($row['carried_points']) }} pts</small>@endif</td>
                                @endif
                                <td class="text-center total-col">{{ num($row['average']) }}</td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row">
            @foreach($results['categories'] as $category)
                @php($rows = $data['categories'][$category->id])
                <div class="col-xl-6">
                    <div class="ibox">
                        <div class="ibox-title">
                            <h5>{{ $division ? $division.' · ' : '' }}{{ $category->name }}
                                <span class="badge {{ $category->statusBadge() }} m-l-xs">{{ ucfirst($category->status) }}</span></h5>
                            <div class="ibox-tools"><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></div>
                        </div>
                        <div class="ibox-content table-responsive">
                            <table class="table table-sm table-hover results-table m-b-none">
                                <thead>
                                <tr>
                                    <th class="text-center">Rank</th><th>Contestant</th>
                                    @foreach($results['judges'] as $judge)<th class="text-center"><small class="text-muted d-block">Seat {{ $judge->pivot->seat ?? '—' }}</small>{{ $judge->name }}</th>@endforeach
                                    <th class="text-center total-col">Average</th>
                                    @if($ranking)<th class="text-center">Rank Sum</th>@endif
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($rows as $row)
                                    <tr>
                                        <td class="text-center"><span class="rank-badge rank-{{ $row['rank'] }}">{{ $row['rank'] ?? '—' }}</span></td>
                                        <td><b>#{{ $row['contestant']->number }}</b> {{ \Illuminate\Support\Str::limit($row['contestant']->name, 24) }}</td>
                                        @foreach($results['judges'] as $judge)
                                            <td class="text-center">
                                                {{ num($row['judge_scores'][$judge->id] ?? null) }}
                                                @if($ranking)<span class="judge-rank">({{ num($row['judge_ranks'][$judge->id] ?? null, 1) }})</span>@endif
                                            </td>
                                        @endforeach
                                        <td class="text-center total-col">{{ num($row['average']) }}</td>
                                        @if($ranking)<td class="text-center">{{ num($row['rank_sum'], 1) }}</td>@endif
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endif
