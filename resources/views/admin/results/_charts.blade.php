{{--
    Chart views of the live results. Rendered on the server like the tables, so they refresh
    live with the same pulse mechanism. Plain HTML/CSS marks (no chart library), so they work
    offline on the event network. Every mark has a hover tooltip ([data-tip]); the Table view
    is the accessible fallback.
--}}
<?php
    $ranking = $event->scoring_method === 'ranking';
    $carry = $results['carry'] ?? null;
    $judges = $results['judges'];
    $categories = $results['categories']->values();
    // Categorical slots in fixed order by category sequence; past 7 categories the rest fold into "Other".
    $folded = $categories->count() > 8;
    $series = $categories->take($folded ? 7 : 8)->map(fn ($c, $i) => ['key' => 'c'.$c->id, 'ids' => [$c->id], 'name' => $c->name, 'slot' => $i + 1])->values()->all();
    if ($folded) {
        $series[] = ['key' => 'other', 'ids' => $categories->slice(7)->pluck('id')->all(), 'name' => 'Other categories', 'slot' => 'other'];
    }
    $label = fn ($c) => '#'.$c->number.' '.$c->name;
    $fmt = fn ($v, $d = 2) => $v === null ? '—' : number_format((float) $v, $d);
?>

@if(! $results || empty($results['divisions']))
    <div class="ibox"><div class="ibox-content text-center text-muted p-xl">No contestants in this round yet.</div></div>
@else
<div class="viz-root">
    @if($carry)
        <p class="text-muted m-b-sm"><i class="fa fa-link"></i> Final score = {{ num(100 - $carry['percent']) }}% {{ $round->name }} + {{ num($carry['percent']) }}% {{ $carry['from']->name }}.</p>
    @endif

    @foreach($results['divisions'] as $division => $data)
        @php($rows = array_values($data['overall']))

        {{-- ================= Standings: ranked bars ================= --}}
        @if($chart === 'standings')
            <div class="ibox">
                <div class="ibox-title">
                    <h5><i class="fa fa-bar-chart"></i> {{ $division ? $division.' · ' : '' }}Standings · {{ $round->name }}</h5>
                    <div class="ibox-tools"><small class="text-muted">{{ $ranking ? 'Ordered by lowest rank sum; bars show the average score' : 'Total score out of 100' }} · updated {{ now()->format('h:i:s A') }}</small></div>
                </div>
                <div class="ibox-content">
                    <div class="viz-bars">
                        @foreach($rows as $row)
                            @php($v = $row['average'])
                            <div class="viz-row">
                                <div class="viz-label"><span class="rank-badge rank-{{ $row['rank'] }}">{{ $row['rank'] ?? '—' }}</span> {{ $label($row['contestant']) }}</div>
                                <div class="viz-track">
                                    @if($v !== null)
                                        <div class="viz-bar viz-seq" style="width: {{ max(0.5, min(100, $v)) }}%" tabindex="0"
                                             data-tip="{{ $label($row['contestant']) }}&#10;Rank {{ $row['rank'] }}&#10;{{ $ranking ? 'Average' : 'Total' }}: {{ $fmt($v) }}{{ $ranking ? "
Rank sum: ".$fmt($row['rank_sum'], 1) : '' }}"></div>
                                    @endif
                                </div>
                                <div class="viz-value">{{ $ranking ? $fmt($row['rank_sum'], 1) : $fmt($v) }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="viz-axis"><span>0</span><span>25</span><span>50</span><span>75</span><span>100</span></div>
                </div>
            </div>
        @endif

        {{-- ================= Breakdown: stacked bars by category ================= --}}
        @if($chart === 'breakdown')
            <div class="ibox">
                <div class="ibox-title">
                    <h5><i class="fa fa-align-left"></i> {{ $division ? $division.' · ' : '' }}Score breakdown by category</h5>
                    <div class="ibox-tools"><small class="text-muted">Points each category adds to the total (out of 100)</small></div>
                </div>
                <div class="ibox-content">
                    <div class="viz-legend">
                        @foreach($series as $s)
                            <span><i class="viz-swatch viz-s{{ $s['slot'] }}"></i>{{ $s['name'] }}</span>
                        @endforeach
                        @if($carry)<span><i class="viz-swatch viz-carry"></i>From {{ $carry['from']->name }}</span>@endif
                    </div>
                    <div class="viz-bars">
                        @foreach($rows as $row)
                            @php($scale = $carry ? (100 - $carry['percent']) / 100 : 1)
                            <div class="viz-row">
                                <div class="viz-label"><span class="rank-badge rank-{{ $row['rank'] }}">{{ $row['rank'] ?? '—' }}</span> {{ $label($row['contestant']) }}</div>
                                <div class="viz-track viz-stack">
                                    @foreach($series as $s)
                                        @php($pts = collect($s['ids'])->sum(fn ($id) => ($row['categories'][$id]['weighted'] ?? 0)) * $scale)
                                        @if($pts > 0)
                                            <div class="viz-seg viz-s{{ $s['slot'] }}" style="width: {{ $pts }}%" tabindex="0"
                                                 data-tip="{{ $label($row['contestant']) }}&#10;{{ $s['name'] }}: {{ $fmt($pts) }} pts{{ count($s['ids']) === 1 ? "
Category average: ".$fmt($row['categories'][$s['ids'][0]]['average']) : '' }}"></div>
                                        @endif
                                    @endforeach
                                    @if($carry && $row['carried_points'] && $row['round_score'] !== null)
                                        <div class="viz-seg viz-carry" style="width: {{ $row['carried_points'] }}%" tabindex="0"
                                             data-tip="{{ $label($row['contestant']) }}&#10;From {{ $carry['from']->name }}: {{ $fmt($row['carried_points']) }} pts ({{ num($carry['percent']) }}% of {{ $fmt($row['carried']) }})"></div>
                                    @endif
                                </div>
                                <div class="viz-value">{{ $fmt($row['average']) }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="viz-axis"><span>0</span><span>25</span><span>50</span><span>75</span><span>100</span></div>
                </div>
            </div>
        @endif

        {{-- ================= Judges: heatmap of each judge's score ================= --}}
        @if($chart === 'judges')
            <?php
                $all = collect($rows)->flatMap(fn ($r) => array_filter($r['judge_scores'], fn ($v) => $v !== null));
                $lo = $all->min(); $hi = $all->max();
                $step = function ($v) use ($lo, $hi) {
                    if ($v === null) return null;
                    if ($hi - $lo < 0.0001) return 4;
                    return 1 + (int) round(($v - $lo) / ($hi - $lo) * 6);   // steps 1..7
                };
            ?>
            <div class="ibox">
                <div class="ibox-title">
                    <h5><i class="fa fa-th"></i> {{ $division ? $division.' · ' : '' }}How each judge scored</h5>
                    <div class="ibox-tools"><small class="text-muted">Colour key below: low → high. Spot judges who disagree.</small></div>
                </div>
                <div class="ibox-content table-responsive">
                    @if($all->isEmpty())
                        <p class="text-muted m-b-none">No scores yet.</p>
                    @else
                        <table class="viz-heat">
                            <thead>
                            <tr><th></th>@foreach($judges as $judge)<th>{{ $judge->name }}</th>@endforeach<th>{{ $ranking ? 'Rank sum' : 'Average' }}</th></tr>
                            </thead>
                            <tbody>
                            @foreach($rows as $row)
                                <tr>
                                    <th class="viz-heat-name"><span class="rank-badge rank-{{ $row['rank'] }}">{{ $row['rank'] ?? '—' }}</span> {{ $label($row['contestant']) }}</th>
                                    @foreach($judges as $judge)
                                        @php($v = $row['judge_scores'][$judge->id] ?? null)
                                        <td class="{{ $v === null ? 'viz-heat-empty' : 'viz-q'.$step($v) }}" tabindex="0"
                                            data-tip="{{ $label($row['contestant']) }}&#10;{{ $judge->name }}: {{ $fmt($v) }}{{ $ranking && isset($row['judge_ranks'][$judge->id]) ? "
Judge rank: ".$fmt($row['judge_ranks'][$judge->id], 1) : '' }}">{{ $fmt($v, 1) }}</td>
                                    @endforeach
                                    <td class="viz-heat-total">{{ $ranking ? $fmt($row['rank_sum'], 1) : $fmt($row['average']) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="viz-ramp-legend"><span>{{ $fmt($lo, 1) }}</span>@for($q = 1; $q <= 7; $q++)<i class="viz-q{{ $q }}"></i>@endfor<span>{{ $fmt($hi, 1) }}</span></div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ================= Categories: small multiples ================= --}}
        @if($chart === 'categories')
            <h3 class="m-b-sm">{{ $division ? $division.' · ' : '' }}Leaders in each category</h3>
            <div class="row">
                @foreach($categories as $i => $category)
                    @php($catRows = array_values($data['categories'][$category->id]))
                    @php($slot = $folded && $i >= 7 ? 'other' : $i + 1)
                    <div class="col-lg-6 col-xl-4">
                        <div class="ibox">
                            <div class="ibox-title"><h5><i class="viz-swatch viz-s{{ $slot }}"></i>{{ $category->name }} <small class="m-l-xs">{{ num($category->weight) }}%</small></h5></div>
                            <div class="ibox-content">
                                <div class="viz-bars viz-bars-sm">
                                    @foreach($catRows as $row)
                                        <div class="viz-row">
                                            <div class="viz-label"><b>{{ $row['rank'] ?? '—' }}</b> {{ $label($row['contestant']) }}</div>
                                            <div class="viz-track">
                                                @if($row['average'] !== null)
                                                    <div class="viz-bar viz-s{{ $slot }}" style="width: {{ max(0.5, min(100, $row['average'])) }}%" tabindex="0"
                                                         data-tip="{{ $label($row['contestant']) }}&#10;{{ $category->name }}: {{ $fmt($row['average']) }}&#10;Rank {{ $row['rank'] }}"></div>
                                                @endif
                                            </div>
                                            <div class="viz-value">{{ $fmt($row['average'], 1) }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ================= Top 3: podium tiles ================= --}}
        @if($chart === 'podium')
            @if($loop->first)@include('partials.event_banner', ['event' => $event])@endif
            @php($top = collect($rows)->filter(fn ($r) => $r['rank'] !== null && $r['rank'] <= 3)->values())
            <div class="ibox">
                <div class="ibox-title"><h5><i class="fa fa-trophy text-warning"></i> {{ $division ? $division.' · ' : '' }}Top 3 · {{ $round->name }}</h5>
                    <div class="ibox-tools"><small class="text-muted">updated {{ now()->format('h:i:s A') }}</small></div></div>
                <div class="ibox-content">
                    @if($top->isEmpty())
                        <p class="text-muted m-b-none">No scores yet.</p>
                    @else
                        <div class="viz-podium">
                            @foreach($top as $row)
                                <div class="viz-podium-tile viz-place-{{ $row['rank'] }}">
                                    <span class="rank-badge rank-{{ $row['rank'] }}">{{ $row['rank'] }}</span>
                                    <img src="{{ $row['contestant']->photoUrl() }}" alt="" class="viz-podium-photo">
                                    <div class="viz-podium-name">{{ $label($row['contestant']) }}</div>
                                    @if($row['contestant']->representing)<small class="text-muted">{{ $row['contestant']->representing }}</small>@endif
                                    <div class="viz-podium-score">{{ $ranking ? $fmt($row['rank_sum'], 1) : $fmt($row['average']) }}</div>
                                    <small class="text-muted">{{ $ranking ? 'rank sum' : 'out of 100' }}</small>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif
    @endforeach
</div>
@endif
