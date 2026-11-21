{{--
    One at a Time: one contestant per screen, built to fit without scrolling.
    Left: the portrait photo (sized to the screen height). Right: number, name, running total, then the score boxes.
    Previous / Next, Save and Submit live in the fixed bar at the bottom of the screen (see sheet.blade.php).
--}}
@if($contestants->isEmpty())
    <div class="text-center text-muted p-lg">No contestants in this round.</div>
@else
    <div class="sheet-strip sheet-strip-compact" role="tablist" aria-label="Contestants">
        @foreach($contestants as $i => $c)
            <button type="button" class="sheet-chip contestant-chip {{ $i === 0 ? 'active' : '' }}" data-contestant="{{ $c->id }}"
                    data-show-panel="#focus-{{ $c->id }}" title="{{ $display['show_names'] ? $c->name : 'Contestant No. '.$c->number }}{{ $c->division ? ' ('.$c->division.')' : '' }}">{{ $c->number }}@if($c->division)<small class="sheet-chip-div">{{ $c->division }}</small>@endif</button>
        @endforeach
    </div>

    @foreach($contestants as $i => $c)
        <div class="sheet-panel {{ $i === 0 ? 'active' : '' }}" id="focus-{{ $c->id }}" data-panel-group="focus" data-contestant="{{ $c->id }}"
             data-position="{{ $i + 1 }}" data-count="{{ $contestants->count() }}">
            <div class="focus-grid {{ $display['show_photos'] ? '' : 'no-photo' }}">
                @if($display['show_photos'])
                    <div class="focus-photo">
                        <img src="{{ $c->photoUrl() }}" class="sheet-photo-portrait" alt="{{ $display['show_names'] ? $c->name : 'Contestant No. '.$c->number }}">
                    </div>
                @endif
                <div class="focus-main">
                    <div class="focus-head">
                        <span class="sheet-number">#{{ $c->number }}</span>
                        <div class="focus-name">
                            <strong class="sheet-name">{{ $display['show_names'] ? $c->name : 'Contestant No. '.$c->number }}</strong>
                            @if($display['show_details'])
                                <small class="text-muted d-block">
                                    @if($c->division)<span class="badge badge-light">{{ $c->division }}</span>@endif
                                    {{ $display['show_names'] ? $c->representing : '' }}
                                </small>
                            @endif
                        </div>
                        @if($display['show_totals'] || $display['show_my_rank'])
                            <div class="focus-total">@include('judge.layouts._summary')</div>
                        @endif
                    </div>
                    @foreach($category->criteria as $criterion)
                        <div class="sheet-field focus-field">
                            <label>{{ $criterion->name }}
                                <small class="text-muted d-block">{{ num($criterion->weight) }}% · score {{ num($criterion->min_score) }}–{{ num($criterion->max_score) }}{{ $criterion->description ? ' · '.$criterion->description : '' }}</small>
                            </label>
                            @include('judge.layouts._input', ['inputClass' => 'score-input-lg'])
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
@endif
