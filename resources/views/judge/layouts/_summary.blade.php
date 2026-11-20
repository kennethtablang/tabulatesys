{{-- Running total and the judge's own rank for one contestant (filled in by JavaScript). --}}
@if($display['show_totals'])
    <span class="sheet-total-wrap"><span class="contestant-total" data-contestant="{{ $c->id }}">—</span><small class="text-muted"> / 100</small></span>
@endif
@if($display['show_my_rank'])
    <span class="badge badge-light contestant-rank m-l-xs" data-contestant="{{ $c->id }}" title="Your ranking of this contestant in this category"></span>
@endif
