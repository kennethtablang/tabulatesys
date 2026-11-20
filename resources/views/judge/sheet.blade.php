@extends('layouts.app')

@section('title', $category->name.' Score Sheet')
@section('heading', $category->name.' — Score Sheet')
@section('breadcrumbs')
    @if($preview)
        <li class="breadcrumb-item"><a href="{{ route('admin.settings') }}">Settings</a></li>
        <li class="breadcrumb-item active"><strong>Judge view preview</strong></li>
    @else
        <li class="breadcrumb-item"><a href="{{ route('judge.dashboard') }}">My Events</a></li>
        <li class="breadcrumb-item"><a href="{{ route('judge.event', $category->event) }}">{{ $category->event->name }}</a></li>
        <li class="breadcrumb-item active"><strong>{{ $category->name }}</strong></li>
    @endif
@endsection

@section('content')
@if($preview)
    <div class="alert alert-info d-flex flex-wrap align-items-center" style="gap:8px">
        <span><i class="fa fa-eye"></i> <b>Preview</b>: this is how judges see <b>{{ $category->name }}</b> ({{ $category->event->name }}). Nothing here is saved.</span>
        <a href="{{ route('admin.settings') }}" class="btn btn-xs btn-white ml-auto"><i class="fa fa-arrow-left"></i> Back to Settings</a>
    </div>
@endif
<div class="alert alert-danger save-banner" id="save-banner"></div>
@if(! $preview)
    @if($submission)
        <div class="alert alert-success"><i class="fa fa-check-circle"></i> You submitted this score sheet on {{ $submission->submitted_at->format('F d, Y h:i A') }}. Ask the administrator if you need to make changes.</div>
    @elseif(! $editable)
        <div class="alert alert-warning"><i class="fa fa-lock"></i> This category is not open for scoring. Scores are read-only.</div>
    @endif
@endif

<div class="ibox">
    <div class="ibox-title d-flex flex-wrap align-items-center" style="gap:8px; padding-right:15px">
        <h5 class="m-0">{{ $category->round->name }} · {{ $category->name }}</h5>
        <div class="ml-auto d-flex align-items-center" style="gap:10px">
            @if($editable)<small id="save-status" class="text-muted"><i class="fa fa-cloud"></i> Every score is saved automatically as you type</small>@endif
            @if($display['allow_switch'] || $preview)
                <div class="dropdown">
                    <button type="button" class="btn btn-xs btn-white dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fa {{ \App\Support\JudgeDisplay::LAYOUTS[$display['layout']][1] }}"></i> {{ \App\Support\JudgeDisplay::LAYOUTS[$display['layout']][0] }}
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <h6 class="dropdown-header">View contestants as</h6>
                        @foreach(\App\Support\JudgeDisplay::LAYOUTS as $key => [$label, $icon])
                            <a class="dropdown-item js-layout-link {{ $display['layout'] === $key ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['layout' => $key]) }}">
                                <i class="fa {{ $icon }} fa-fw"></i> {{ $label }}
                                @if($display['default_layout'] === $key)<small class="text-muted">(default)</small>@endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
    <div class="ibox-content p-sm">
        <form method="POST" action="{{ $preview ? '#' : route('judge.scores.save', $category) }}" id="score-form" class="sheet-layout-{{ $display['layout'] }}">
            @csrf
            <input type="hidden" name="action" value="save" id="form-action">
            @include('judge.layouts.'.($display['layout'] === 'compact' ? 'table' : $display['layout']))
            @if($editable)
                <div class="sheet-sticky-actions d-flex align-items-center">
                    <span class="text-muted" id="completion"></span>
                    <div class="ml-auto">
                        <button type="submit" class="btn btn-white"><i class="fa fa-save"></i> Save Draft</button>
                        <button type="button" class="btn btn-primary" id="btn-submit"><i class="fa fa-paper-plane"></i> Submit Final Scores</button>
                    </div>
                </div>
            @else
                <div class="m-t-sm text-muted" id="completion"></div>
            @endif
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function ($) {
    var autosaveUrl = @json(route('judge.scores.autosave', $category));
    var editable = @json($editable);
    // Unsaved values are also kept in this browser, so nothing is lost if the network drops,
    // the server restarts or the page is closed before a save finishes.
    var storeKey = 'tms-pending-' + @json($category->id) + '-' + @json(auth()->id());
    var DEBOUNCE_MS = 300;

    var $status = $('#save-status'), $banner = $('#save-banner');
    var pending = {};   // cellKey -> value not yet confirmed by the server
    var timers = {};    // cellKey -> debounce timer
    var inflight = {};  // cellKey -> value currently being sent
    var retryDelay = 1000, retryTimer = null, stopped = false, lastSavedAt = null;

    function cellKey(input) { return input.dataset.contestant + ':' + input.dataset.criterion; }
    function inputFor(key) {
        var p = key.split(':');
        return document.querySelector('.score-input[data-contestant="' + p[0] + '"][data-criterion="' + p[1] + '"]');
    }

    function persist() {
        try {
            if ($.isEmptyObject(pending)) { localStorage.removeItem(storeKey); }
            else { localStorage.setItem(storeKey, JSON.stringify(pending)); }
        } catch (e) { /* storage unavailable: server saves still work */ }
    }
    function restore() {
        try { return JSON.parse(localStorage.getItem(storeKey) || '{}') || {}; } catch (e) { return {}; }
    }

    // Totals, the judge's own ranking and progress markers, for whichever layout is shown.
    function recalc() {
        var filled = 0, total = 0, perContestant = {}, perCriterion = {};
        $('.score-input').each(function () {
            var c = this.dataset.contestant, k = this.dataset.criterion;
            perContestant[c] = perContestant[c] || {sum: 0, any: false, filled: 0, count: 0};
            perCriterion[k] = perCriterion[k] || {filled: 0, count: 0};
            perContestant[c].count++; perCriterion[k].count++; total++;
            var v = parseFloat(this.value);
            if (!isNaN(v)) {
                perContestant[c].any = true; perContestant[c].filled++; perCriterion[k].filled++; filled++;
                perContestant[c].sum += (v / parseFloat(this.dataset.max)) * parseFloat(this.dataset.weight);
            }
        });

        var ranked = [];
        $.each(perContestant, function (id, p) {
            $('.contestant-total[data-contestant="' + id + '"]').text(p.any ? (Math.round(p.sum * 100) / 100).toFixed(2) : '—');
            $('.contestant-chip[data-contestant="' + id + '"]')
                .toggleClass('is-done', p.filled === p.count).toggleClass('is-partial', p.filled > 0 && p.filled < p.count);
            if (p.any) { ranked.push({id: id, sum: Math.round(p.sum * 10000)}); }
        });
        $.each(perCriterion, function (id, p) {
            $('.criterion-progress[data-criterion="' + id + '"]').text('(' + p.filled + '/' + p.count + ')');
            $('.criterion-chip[data-criterion="' + id + '"]').toggleClass('is-done', p.filled === p.count);
        });

        // Judge's own ranking (ties share a place).
        ranked.sort(function (a, b) { return b.sum - a.sum; });
        $('.contestant-rank').text('');
        ranked.forEach(function (r, i) {
            var place = (i > 0 && r.sum === ranked[i - 1].sum) ? ranked[i - 1].place : i + 1;
            r.place = place;
            $('.contestant-rank[data-contestant="' + r.id + '"]').text('Rank ' + place);
        });

        $('#completion').text(filled + ' of ' + total + ' scores entered');
        return filled === total;
    }

    // Layouts with panels (One at a Time, By Criterion): show one panel of a group.
    function showPanel(panel) {
        var $panel = $(panel);
        if (!$panel.length || $panel.hasClass('active')) { return; }
        $('.sheet-panel[data-panel-group="' + $panel.data('panel-group') + '"]').removeClass('active');
        $panel.addClass('active');
        $('[data-show-panel]').each(function () {
            var target = $(this).attr('data-show-panel');
            if ($(this).is('.sheet-chip')) { $(this).toggleClass('active', target === '#' + panel.id); }
        });
        var chip = document.querySelector('.sheet-chip[data-show-panel="#' + panel.id + '"]');
        if (chip && chip.scrollIntoView) { chip.scrollIntoView({block: 'nearest', inline: 'center'}); }
    }
    $(document).on('click', '[data-show-panel]', function () {
        var panel = document.querySelector($(this).attr('data-show-panel'));
        showPanel(panel);
        var first = panel && panel.querySelector('.score-input:enabled');
        if (first) { first.focus({preventScroll: true}); }
    });

    // Remember layout picks without leaving unsaved scores behind.
    $(document).on('click', '.js-layout-link', function (e) {
        if (!$.isEmptyObject(pending)) {
            e.preventDefault();
            flushAll();
            toastr.info('Saving your last scores first. Try again in a moment.');
        }
    });

    // Returns '' when cleared, the value when valid, or null when it is not a usable
    // number yet (e.g. "8." while typing, or outside the allowed range).
    function readValue(input) {
        if (input.validity && input.validity.badInput) { return null; }
        if (input.value === '') { return ''; }
        var v = parseFloat(input.value);
        if (isNaN(v) || v < parseFloat(input.dataset.min) || v > parseFloat(input.dataset.max)) { return null; }
        return input.value;
    }

    function showStatus() {
        if (stopped) { return; }
        var waiting = Object.keys(pending).length;
        if (!waiting) {
            $banner.removeClass('show');
            if (lastSavedAt) { $status.html('<i class="fa fa-check text-navy"></i> All scores saved · ' + lastSavedAt); }
        } else if (retryTimer) {
            $status.html('<i class="fa fa-exclamation-triangle text-danger"></i> ' + waiting + ' score(s) not saved yet, retrying…');
        } else {
            $status.html('<i class="fa fa-spinner fa-spin"></i> Saving…');
        }
    }

    function queue(input, delay) {
        var key = cellKey(input), value = readValue(input);
        clearTimeout(timers[key]);
        if (value === null) { return; }          // wait until the judge finishes a valid number
        if (pending[key] === undefined && input.dataset.saved === value) { return; } // nothing changed
        pending[key] = value;
        persist();
        $(input).removeClass('is-saved is-error').addClass('is-pending');
        showStatus();
        timers[key] = setTimeout(function () { send(key); }, delay);
    }

    function send(key) {
        if (stopped || !(key in pending) || (key in inflight)) { return; }
        var value = pending[key], parts = key.split(':'), input = inputFor(key);
        inflight[key] = value;
        $(input).removeClass('is-pending').addClass('is-saving');

        $.ajax({
            url: autosaveUrl, method: 'POST', dataType: 'json', timeout: 8000,
            data: {contestant_id: parts[0], criterion_id: parts[1], score: value === '' ? null : value}
        }).done(function (res) {
            delete inflight[key];
            retryDelay = 1000;
            lastSavedAt = res.at;
            input.dataset.saved = value;
            if (pending[key] === value) {
                delete pending[key];
                persist();
                $(input).removeClass('is-saving is-pending is-error').addClass('is-saved');
            } else {
                send(key);                       // the judge kept typing while this was saving
            }
            showStatus();
        }).fail(function (xhr) {
            delete inflight[key];
            $(input).removeClass('is-saving');
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || '';

            if (xhr.status === 422) {            // rejected value: keep it on screen, flag it
                if (pending[key] === value) { delete pending[key]; persist(); }
                $(input).addClass('is-error');
                toastr.warning(msg || 'Invalid score.');
                showStatus();
            } else if (xhr.status === 423 || xhr.status === 403) {
                pending = {};
                persist();
                stop('<i class="fa fa-lock"></i> ' + (msg || 'This score sheet is locked.') + ' Your entries can no longer be changed.');
                $('.score-input').prop('disabled', true);
            } else if (xhr.status === 419 || xhr.status === 401) {
                stop('<i class="fa fa-sign-in"></i> Your session has expired. Your unsaved scores are kept on this computer. ' +
                     '<a href="" class="alert-link">Reload the page</a> (log in again if asked) and they will be saved.');
            } else {                             // network down / server busy: keep retrying
                $(input).addClass('is-pending');
                $banner.html('<i class="fa fa-wifi"></i> Cannot reach the tabulation server. Keep scoring: your scores are kept on this computer and will be sent automatically when the connection returns.').addClass('show');
                scheduleRetry();
            }
        });
    }

    function scheduleRetry() {
        if (retryTimer || stopped) { showStatus(); return; }
        retryTimer = setTimeout(function () {
            retryTimer = null;
            Object.keys(pending).forEach(send);
        }, retryDelay);
        retryDelay = Math.min(retryDelay * 2, 10000);
        showStatus();
    }

    function stop(html) {
        stopped = true;
        $banner.html(html).addClass('show');
        $status.html('<i class="fa fa-exclamation-triangle text-danger"></i> Not saving');
    }

    function flushAll() {
        Object.keys(timers).forEach(function (k) { clearTimeout(timers[k]); });
        Object.keys(pending).forEach(send);
    }

    // Remember what the server already has, so unchanged cells are not re-sent.
    $('.score-input').each(function () { this.dataset.saved = this.value; });

    // Every keystroke (typing, deleting, arrow keys, paste) schedules a save of that cell.
    $(document).on('input', '.score-input', function () {
        var value = readValue(this);
        $(this).toggleClass('is-error', value === null);
        recalc();
        queue(this, DEBOUNCE_MS);
    });

    // Leaving a cell saves it immediately.
    $(document).on('change', '.score-input', function () {
        if (readValue(this) === null) {
            toastr.warning('Score must be between ' + this.dataset.min + ' and ' + this.dataset.max + '.');
            return;
        }
        queue(this, 0);
    });

    // Stop the mouse wheel from silently changing a focused score.
    document.querySelectorAll('.score-input').forEach(function (el) {
        el.addEventListener('wheel', function (e) { if (document.activeElement === el) { e.preventDefault(); } }, {passive: false});
    });

    // Enter moves to the next score box (opening the next contestant / criterion panel if needed).
    $(document).on('keydown', '.score-input', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var $inputs = $('.score-input:enabled');
            var next = $inputs.get($inputs.index(this) + 1);
            if (!next) { return; }
            var panel = $(next).closest('.sheet-panel').get(0);
            if (panel) { showPanel(panel); }
            $(next).focus().select();
        }
    });

    window.addEventListener('online', function () { retryDelay = 1000; flushAll(); });
    window.addEventListener('beforeunload', function (e) {
        if (window.tmsReloading) { return; }   // admin-triggered reload: pending scores stay in this browser and resend
        if (!$.isEmptyObject(pending)) { e.preventDefault(); e.returnValue = ''; }
    });
    window.tmsSheet = {
        busy: function () { return !$.isEmptyObject(pending) || !$.isEmptyObject(inflight); },
        flush: flushAll
    };

    // Save Draft / Submit post the whole sheet, so locally kept values are no longer needed.
    $('#score-form').on('submit', function () {
        Object.keys(timers).forEach(function (k) { clearTimeout(timers[k]); });
        pending = {};
        persist();
    });

    $('#btn-submit').on('click', function () {
        if (!recalc()) {
            swal('Incomplete', 'Please enter a score for every contestant and criterion before submitting.', 'warning');
            return;
        }
        if ($('.score-input.is-error').length) {
            swal('Invalid scores', 'Some scores are outside the allowed range.', 'error');
            return;
        }
        swal({
            title: 'Submit final scores?',
            text: 'After submitting you can no longer change your scores for this category.',
            type: 'warning', showCancelButton: true, confirmButtonColor: window.tmsAccent(),
            confirmButtonText: 'Yes, submit', closeOnConfirm: true
        }, function () {
            $('#form-action').val('submit');
            $('#score-form').trigger('submit');
        });
    });

    // Re-send anything that was typed but not confirmed before the page was closed or reloaded.
    var saved = restore();
    if (editable) {
        $.each(saved, function (key, value) {
            var input = inputFor(key);
            if (!input || input.disabled) { return; }
            input.value = value;
            pending[key] = value;
            $(input).addClass('is-pending');
        });
        if (!$.isEmptyObject(pending)) {
            toastr.info('Sending ' + Object.keys(pending).length + ' score(s) that were not saved earlier.');
            persist();
            flushAll();
        }
    } else {
        persist();   // sheet is locked/submitted: drop anything stale
    }

    recalc();
})(jQuery);
</script>
@endpush
