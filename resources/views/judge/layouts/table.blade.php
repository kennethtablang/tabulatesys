{{-- Score Table / Compact List: contestants in rows, criteria in columns. --}}
@php($compact = $display['layout'] === 'compact')
<div class="table-responsive">
    <table class="table table-bordered table-hover score-sheet m-b-none {{ $compact ? 'table-sm score-sheet-compact' : '' }}">
        <thead class="thead-light">
        <tr>
            <th>Contestant</th>
            @foreach($category->criteria as $criterion)
                <th class="text-center" @if($criterion->description) title="{{ $criterion->description }}" @endif>{{ $criterion->name }}<small>{{ num($criterion->weight) }}% · {{ num($criterion->min_score) }}–{{ num($criterion->max_score) }}</small>@if($criterion->description && ! $compact)<small class="criterion-desc">{{ $criterion->description }}</small>@endif</th>
            @endforeach
            @if($display['show_totals'] || $display['show_my_rank'])
                <th class="text-center">{{ $display['show_totals'] ? 'Total' : 'Rank' }}<small>{{ $display['show_totals'] ? 'out of 100' : 'your ranking' }}</small></th>
            @endif
        </tr>
        </thead>
        <tbody>
        @forelse($contestants as $c)
            <tr data-contestant="{{ $c->id }}">
                <td>@include('judge.layouts._contestant', ['size' => $compact ? 'small' : $display['photo_size']])</td>
                @foreach($category->criteria as $criterion)
                    <td class="text-center">@include('judge.layouts._input')</td>
                @endforeach
                @if($display['show_totals'] || $display['show_my_rank'])
                    <td class="text-center" style="white-space:nowrap">@include('judge.layouts._summary')</td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ 2 + $category->criteria->count() }}" class="text-center text-muted">No contestants in this round.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
