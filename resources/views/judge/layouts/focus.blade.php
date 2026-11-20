{{-- One at a Time: a single contestant fills the screen; number strip + Previous / Next. --}}
@if($contestants->isEmpty())
    <div class="text-center text-muted p-lg">No contestants in this round.</div>
@else
    <div class="sheet-strip" role="tablist" aria-label="Contestants">
        @foreach($contestants as $i => $c)
            <button type="button" class="sheet-chip contestant-chip {{ $i === 0 ? 'active' : '' }}" data-contestant="{{ $c->id }}"
                    data-show-panel="#focus-{{ $c->id }}" title="{{ $display['show_names'] ? $c->name : 'Contestant No. '.$c->number }}{{ $c->division ? ' ('.$c->division.')' : '' }}">{{ $c->number }}@if($c->division)<small class="sheet-chip-div">{{ $c->division }}</small>@endif</button>
        @endforeach
    </div>

    @foreach($contestants as $i => $c)
        <div class="sheet-panel {{ $i === 0 ? 'active' : '' }}" id="focus-{{ $c->id }}" data-panel-group="focus" data-contestant="{{ $c->id }}">
            <div class="row">
                <div class="col-md-5 text-center m-b-md">
                    @include('judge.layouts._contestant', ['size' => 'xl', 'stacked' => true])
                    @if($display['show_totals'] || $display['show_my_rank'])
                        <div class="sheet-focus-total m-t-sm">@include('judge.layouts._summary')</div>
                    @endif
                </div>
                <div class="col-md-7">
                    @foreach($category->criteria as $criterion)
                        <div class="sheet-field sheet-field-lg">
                            <label>{{ $criterion->name }}
                                <small class="text-muted d-block">{{ num($criterion->weight) }}% · score {{ num($criterion->min_score) }}–{{ num($criterion->max_score) }}{{ $criterion->description ? ' · '.$criterion->description : '' }}</small>
                            </label>
                            @include('judge.layouts._input', ['inputClass' => 'score-input-lg'])
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="d-flex m-t-sm">
                @if($i > 0)
                    <button type="button" class="btn btn-white" data-show-panel="#focus-{{ $contestants[$i - 1]->id }}"><i class="fa fa-chevron-left"></i> Previous</button>
                @endif
                <span class="ml-auto text-muted align-self-center m-r-sm">{{ $i + 1 }} of {{ $contestants->count() }}</span>
                @if($i < $contestants->count() - 1)
                    <button type="button" class="btn btn-primary" data-show-panel="#focus-{{ $contestants[$i + 1]->id }}">Next <i class="fa fa-chevron-right"></i></button>
                @endif
            </div>
        </div>
    @endforeach
@endif
