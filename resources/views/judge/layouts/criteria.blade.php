{{-- By Criterion: one criterion at a time, every contestant listed. --}}
@php($criteria = $category->criteria->values())
<div class="sheet-strip" role="tablist" aria-label="Criteria">
    @foreach($criteria as $i => $criterion)
        <button type="button" class="sheet-chip sheet-chip-wide criterion-chip {{ $i === 0 ? 'active' : '' }}" data-criterion="{{ $criterion->id }}"
                data-show-panel="#crit-{{ $criterion->id }}">{{ $criterion->name }} <span class="criterion-progress" data-criterion="{{ $criterion->id }}"></span></button>
    @endforeach
</div>

@foreach($criteria as $i => $criterion)
    <div class="sheet-panel {{ $i === 0 ? 'active' : '' }}" id="crit-{{ $criterion->id }}" data-panel-group="criteria">
        <div class="m-b-sm">
            <h3 class="m-b-none">{{ $criterion->name }} <span class="badge badge-primary">{{ num($criterion->weight) }}%</span></h3>
            <small class="text-muted">Score {{ num($criterion->min_score) }}–{{ num($criterion->max_score) }}{{ $criterion->description ? ' · '.$criterion->description : '' }}</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover score-sheet m-b-none">
                <tbody>
                @forelse($contestants as $c)
                    <tr data-contestant="{{ $c->id }}">
                        <td>@include('judge.layouts._contestant')</td>
                        <td class="text-center" style="width:140px">@include('judge.layouts._input')</td>
                        @if($display['show_totals'] || $display['show_my_rank'])
                            <td class="text-right" style="width:150px;white-space:nowrap">@include('judge.layouts._summary')</td>
                        @endif
                    </tr>
                @empty
                    <tr><td class="text-center text-muted">No contestants in this round.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex m-t-sm">
            @if($i > 0)
                <button type="button" class="btn btn-white" data-show-panel="#crit-{{ $criteria[$i - 1]->id }}"><i class="fa fa-chevron-left"></i> {{ $criteria[$i - 1]->name }}</button>
            @endif
            @if($i < $criteria->count() - 1)
                <button type="button" class="btn btn-primary ml-auto" data-show-panel="#crit-{{ $criteria[$i + 1]->id }}">{{ $criteria[$i + 1]->name }} <i class="fa fa-chevron-right"></i></button>
            @endif
        </div>
    </div>
@endforeach
