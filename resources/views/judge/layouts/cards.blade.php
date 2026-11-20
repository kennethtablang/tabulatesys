{{-- Contestant Cards: one card per contestant with its own score boxes. --}}
<div class="row">
    @forelse($contestants as $c)
        <div class="col-md-6 col-xl-4 m-b-md">
            <div class="sheet-card" data-contestant="{{ $c->id }}">
                <div class="sheet-card-head">
                    @include('judge.layouts._contestant', ['size' => $display['photo_size'] === 'small' ? 'medium' : 'large', 'stacked' => true])
                </div>
                <div class="sheet-card-body">
                    @foreach($category->criteria as $criterion)
                        <div class="sheet-field">
                            <label @if($criterion->description) title="{{ $criterion->description }}" @endif>{{ $criterion->name }}<small class="text-muted d-block">{{ num($criterion->weight) }}% · {{ num($criterion->min_score) }}–{{ num($criterion->max_score) }}{{ $criterion->description ? ' · '.$criterion->description : '' }}</small></label>
                            @include('judge.layouts._input')
                        </div>
                    @endforeach
                </div>
                @if($display['show_totals'] || $display['show_my_rank'])
                    <div class="sheet-card-foot">@include('judge.layouts._summary')</div>
                @endif
            </div>
        </div>
    @empty
        <div class="col-12 text-center text-muted p-lg">No contestants in this round.</div>
    @endforelse
</div>
