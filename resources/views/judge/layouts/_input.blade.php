{{-- One score box. Every layout uses this so autosave works the same everywhere. --}}
<input type="number" class="form-control score-input {{ $display['input_size'] === 'large' ? 'score-input-lg' : '' }} {{ $inputClass ?? '' }}"
       name="scores[{{ $c->id }}][{{ $criterion->id }}]"
       value="{{ old("scores.$c->id.$criterion->id", $scores[$c->id][$criterion->id] ?? '') }}"
       min="{{ $criterion->min_score }}" max="{{ $criterion->max_score }}" step="0.01"
       placeholder="{{ num($criterion->min_score, 0) }}–{{ num($criterion->max_score, 0) }}"
       aria-label="{{ $criterion->name }} score for contestant {{ $c->number }}"
       data-contestant="{{ $c->id }}" data-criterion="{{ $criterion->id }}"
       data-weight="{{ $criterion->weight }}" data-max="{{ $criterion->max_score }}" data-min="{{ $criterion->min_score }}"
       @disabled(! $editable) inputmode="decimal" autocomplete="off">
