@extends('layouts.app')

@section('title', 'Settings')
@section('heading', 'Settings')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active"><strong>Settings</strong></li>
@endsection

@php($L = \App\Support\JudgeDisplay::class)
@section('actions')
    <form method="POST" action="{{ route('admin.judges.refresh') }}" class="d-inline js-confirm"
          data-confirm="Reload every judge's screen now? Unsaved scores are saved first." data-type="info" data-color="#1ab394" data-button="Refresh now">
        @csrf
        <button class="btn btn-white"><i class="fa fa-refresh"></i> Refresh all judges' screens</button>
    </form>
@endsection

@section('content')
<div class="alert alert-info p-xs"><i class="fa fa-bolt"></i> Changes here reach the judges' screens by themselves within a few seconds. Nobody needs to refresh.</div>
@include('admin.settings._logos')

<form method="POST" action="{{ route('admin.settings.update') }}" id="settings-form">
    @csrf @method('PUT')

    {{-- ---------------- Appearance ---------------- --}}
    @php($accent = old('accent', $general['accent']))
    <div class="ibox">
        <div class="ibox-title"><h5><i class="fa fa-paint-brush"></i> Appearance: highlight colour</h5></div>
        <div class="ibox-content">
            <p class="text-muted m-b-sm">Used for buttons, badges, the active menu item, links, progress bars and the judges' score sheet on every screen. Pick one and the page previews it right away. Click <b>Save Settings</b> to apply it for everyone.</p>
            <div class="accent-swatches" role="radiogroup" aria-label="Highlight colour">
                @foreach(\App\Support\Theme::PRESETS as $hex => $label)
                    <label class="accent-swatch {{ $accent === $hex ? 'checked' : '' }}" title="{{ $label }}">
                        <input type="radio" name="accent_preset" value="{{ $hex }}" @checked($accent === $hex)>
                        <span style="background: {{ $hex }}"></span>
                        <small>{{ $label }}</small>
                    </label>
                @endforeach
            </div>
            <div class="d-flex flex-wrap align-items-center m-t-sm" style="gap:10px">
                <label class="m-0 font-bold" for="accent-custom">Custom colour</label>
                <input type="color" id="accent-custom" value="{{ $accent }}" class="accent-picker" aria-label="Custom highlight colour">
                <input type="text" name="accent" id="accent-hex" value="{{ $accent }}" class="form-control form-control-sm" style="width:110px" maxlength="7" pattern="#[0-9a-fA-F]{6}" required>
                <span class="m-l-sm">Preview:</span>
                <button type="button" class="btn btn-sm btn-primary">Button</button>
                <span class="badge badge-primary">Badge</span>
                <span class="text-navy font-bold">Highlighted text</span>
                <div class="progress progress-small" style="width:120px;margin:0"><div class="progress-bar" style="width:65%"></div></div>
                <button type="button" class="btn btn-xs btn-white" id="accent-reset">Reset to default</button>
            </div>
        </div>
    </div>

    {{-- ---------------- Judge score sheet ---------------- --}}
    <div class="ibox">
        <div class="ibox-title">
            <h5><i class="fa fa-gavel"></i> Judge score sheet: how judges see the contestants</h5>
        </div>
        <div class="ibox-content">
            <label class="font-bold">Default layout</label>
            <p class="text-muted m-b-sm">Used by every event unless an event picks its own layout (Event → Edit).</p>
            <div class="row">
                @foreach($L::LAYOUTS as $key => [$label, $icon, $help])
                    <div class="col-sm-6 col-lg-4 col-xl m-b-sm">
                        <label class="layout-option {{ old('sheet_layout', $display['sheet_layout']) === $key ? 'checked' : '' }}">
                            <input type="radio" name="sheet_layout" value="{{ $key }}" @checked(old('sheet_layout', $display['sheet_layout']) === $key)>
                            <strong><i class="fa {{ $icon }}"></i> {{ $label }}</strong>
                            <div class="layout-thumb layout-thumb-{{ $key }}" aria-hidden="true">
                                @switch($key)
                                    @case('table') @case('compact')
                                        @for($r = 0; $r < ($key === 'compact' ? 16 : 12); $r++)<i class="{{ $r % 4 ? '' : 'on' }}"></i>@endfor
                                        @break
                                    @case('cards')
                                        @for($r = 0; $r < 6; $r++)<i></i>@endfor
                                        @break
                                    @case('focus')
                                        <i class="on"></i><i></i>
                                        @break
                                    @case('criteria')
                                        @for($r = 0; $r < 8; $r++)<i class="{{ $r % 2 ? 'on' : '' }}"></i>@endfor
                                        @break
                                @endswitch
                            </div>
                            <small class="text-muted">{{ $help }}</small>
                        </label>
                    </div>
                @endforeach
            </div>

            <hr>
            <div class="row">
                <div class="col-lg-6">
                    <label class="font-bold">What judges see</label>
                    @foreach([
                        'show_photos' => ['Contestant photos', 'Not shown in the Compact List.'],
                        'show_names' => ['Contestant names', 'Turn off for blind judging: judges only see "Contestant No. 5".'],
                        'show_details' => ['Division and who they represent', null],
                        'show_totals' => ['Running total out of 100 per contestant', null],
                        'show_my_rank' => ["The judge's own ranking", 'e.g. "Rank 2" next to each contestant, from that judge\'s scores only.'],
                        'allow_switch' => ['Judges may switch layouts themselves', 'Adds a "View contestants as" menu on the score sheet. Their choice is remembered until they log out.'],
                    ] as $name => [$label, $help])
                        <div class="m-b-xs">
                            <label class="m-0"><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $display[$name]))> {{ $label }}</label>
                            @if($help)<br><small class="text-muted" style="margin-left:18px">{{ $help }}</small>@endif
                        </div>
                    @endforeach
                </div>
                <div class="col-lg-6">
                    <div class="form-group">
                        <label class="font-bold">Photo size</label>
                        <select name="photo_size" class="form-control">
                            @foreach($L::PHOTO_SIZES as $value => $label)<option value="{{ $value }}" @selected(old('photo_size', $display['photo_size']) === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-bold">Order of contestants</label>
                        <select name="contestant_order" class="form-control">
                            @foreach($L::ORDERS as $value => $label)<option value="{{ $value }}" @selected(old('contestant_order', $display['contestant_order']) === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-bold">Score box size</label>
                        <select name="input_size" class="form-control">
                            @foreach($L::INPUT_SIZES as $value => $label)<option value="{{ $value }}" @selected(old('input_size', $display['input_size']) === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="ibox-footer d-flex flex-wrap align-items-center" style="gap:8px">
            @if($previewCategories->isNotEmpty())
                <span class="text-muted">Preview with</span>
                <select id="preview-category" class="form-control form-control-sm" style="width:auto;max-width:320px">
                    @foreach($previewCategories as $pc)
                        <option value="{{ $pc->id }}">{{ $pc->event->name }} · {{ $pc->name }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-sm btn-white" id="btn-preview"><i class="fa fa-eye"></i> Preview judge view</button>
                <small class="text-muted">(save first to preview the display options)</small>
            @endif
            <button class="btn btn-primary ml-auto"><i class="fa fa-check"></i> Save Settings</button>
        </div>
    </div>

    {{-- ---------------- General ---------------- --}}
    <div class="ibox">
        <div class="ibox-title"><h5><i class="fa fa-cog"></i> General</h5></div>
        <div class="ibox-content">
            <div class="row">
                <div class="col-md-6 form-group">
                    <label class="font-bold">System name</label>
                    <input name="app_name" class="form-control" value="{{ old('app_name', $general['app_name']) }}" maxlength="80" required>
                    <small class="text-muted">Shown in the top bar, browser tabs and the login page.</small>
                </div>
                <div class="col-md-6 form-group">
                    <label class="font-bold">Organization / host</label>
                    <input name="organization" class="form-control" value="{{ old('organization', $general['organization']) }}" maxlength="120" placeholder="e.g. Student Council of ABC University">
                    <small class="text-muted">Shown on the login page and on printed result sheets.</small>
                </div>
            </div>
        </div>
        <div class="ibox-footer text-right">
            <button class="btn btn-primary"><i class="fa fa-check"></i> Save Settings</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    // Logos: "every event" hides the per-event picks
    $(document).on('change', '.js-all-events', function () {
        $(this).closest('form').find('.js-event-picks').toggle(!this.checked);
    });
    // Logo height slider + per-logo size: live preview
    $('#heading-height').on('input change', function () {
        $('#heading-height-value').text(this.value);
        $('.logo-height-preview').css('--preview-h', this.value + 'px');
    });
    $(document).on('input change', '.js-logo-scale', function () {
        var v = Math.max(40, Math.min(250, parseInt(this.value || 100, 10)));
        $('.logo-height-preview img[data-logo="' + this.dataset.logo + '"]').css('--logo-scale', v / 100);
    });

    // Replace image: preview the new file before saving
    $(document).on('change', '.logo-replace', function () {
        var file = this.files && this.files[0];
        if (file) { $(this).closest('.logo-item').find('.logo-preview img').attr('src', URL.createObjectURL(file)); }
    });

    $('input[name=sheet_layout]').on('change', function () {
        $('.layout-option').removeClass('checked');
        $(this).closest('.layout-option').addClass('checked');
    });
    // ---- Highlight colour: live preview (same maths as App\Support\Theme) ----
    (function () {
        function rgb(h) { return [1, 3, 5].map(function (i) { return parseInt(h.substr(i, 2), 16); }); }
        function hex(c) { return '#' + c.map(function (v) { v = Math.round(Math.max(0, Math.min(255, v))); return (v < 16 ? '0' : '') + v.toString(16); }).join(''); }
        function shade(h, a) { var t = a < 0 ? 0 : 255, k = Math.abs(a); return hex(rgb(h).map(function (c) { return c + (t - c) * k; })); }
        function lum(h) { var l = rgb(h).map(function (c) { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); }); return 0.2126 * l[0] + 0.7152 * l[1] + 0.0722 * l[2]; }
        function contrast(a, b) { var x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
        function readable(h, surface, target, dir) { for (var i = 0; i < 20 && contrast(h, surface) < target; i++) { h = shade(h, 0.08 * dir); } return h; }
        // Same as Theme::fill(): nearest shade whose text reads >= 4.5:1 and that stands out from the page.
        function fill(h, surface, surfaceMin) {
            var best = null;
            [['#ffffff', 0], ['#16181b', 3]].forEach(function (pair) {
                for (var k = 0; k <= 10; k++) {
                    var steps = k === 0 ? [0] : [-k, k];
                    for (var j = 0; j < steps.length; j++) {
                        var c = shade(h, steps[j] * 0.06);
                        if (contrast(pair[0], c) >= 4.5 && contrast(c, surface) >= surfaceMin) {
                            if (!best || k + pair[1] < best.cost) { best = {fill: c, on: pair[0], cost: k + pair[1]}; }
                            return;
                        }
                    }
                }
            });
            return best || {fill: h, on: '#ffffff'};
        }

        function apply(h) {
            if (!/^#[0-9a-fA-F]{6}$/.test(h)) { return; }
            h = h.toLowerCase();
            var dark = document.documentElement.getAttribute('data-theme') === 'dark';
            var c = rgb(h), root = document.documentElement.style;
            var f = dark ? fill(h, '#1e2229', 3.0) : fill(h, '#ffffff', 1.3);
            var darker = f.on === '#ffffff' || !dark;
            root.setProperty('--accent', h);
            root.setProperty('--accent-rgb', c.join(', '));
            root.setProperty('--accent-fill', f.fill);
            root.setProperty('--accent-on', f.on);
            root.setProperty('--accent-contrast', f.on);
            root.setProperty('--accent-hover', shade(f.fill, darker ? -0.10 : 0.10));
            root.setProperty('--accent-active', shade(f.fill, darker ? -0.18 : 0.18));
            root.setProperty('--accent-ink', dark ? readable(h, '#1e2229', 4.5, 1) : readable(h, '#ffffff', 4.5, -1));
            $('#accent-hex').val(h);
            $('#accent-custom').val(h);
            $('.accent-swatch').each(function () {
                var on = $(this).find('input').val() === h;
                $(this).toggleClass('checked', on).find('input').prop('checked', on);
            });
        }
        $('input[name=accent_preset]').on('change', function () { apply(this.value); });
        $('#accent-custom').on('input change', function () { apply(this.value); });
        $('#accent-hex').on('input', function () { apply(this.value.trim()); });
        $('#accent-reset').on('click', function () { apply(@json(\App\Support\Theme::DEFAULT)); });
        $(document).on('click', '[data-theme-toggle]', function () { setTimeout(function () { apply($('#accent-hex').val()); }, 0); });
    })();

    $('#btn-preview').on('click', function () {
        var layout = $('input[name=sheet_layout]:checked').val();
        window.open(@json(route('admin.settings.preview')) + '?category=' + $('#preview-category').val() + '&layout=' + layout, '_blank');
    });
</script>
@endpush
