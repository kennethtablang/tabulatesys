/* Tabulation Management System — shared UI behaviour */
(function ($) {
    'use strict';

    $.ajaxSetup({headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}});

    // Current highlight colour (Settings → Appearance).
    window.tmsAccent = function () {
        return (getComputedStyle(document.documentElement).getPropertyValue('--accent') || '#1ab394').trim();
    };

    // Logout links: ask first ("second thought"), and save a judge's pending scores before leaving.
    $(document).on('click', '.js-logout', function (e) {
        e.preventDefault();
        swal({
            title: 'Log out?',
            text: 'Are you really sure you want to logout?',
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: window.tmsAccent ? window.tmsAccent() : '#1ab394',
            confirmButtonText: 'Yes, log out',
            cancelButtonText: 'Stay',
            closeOnConfirm: true
        }, function () {
            var tries = 0;
            (function go() {
                var sheet = window.tmsSheet;
                if (sheet && sheet.busy() && tries++ < 10) { sheet.flush(); return setTimeout(go, 500); }
                window.tmsReloading = true;
                $('#logout-form').trigger('submit');
            })();
        });
    });

    // Confirm destructive/important forms with SweetAlert:  <form class="js-confirm" data-confirm="...">
    $(document).on('submit', 'form.js-confirm', function (e) {
        var form = this;
        if (form.dataset.confirmed === '1') { return true; }
        e.preventDefault();
        swal({
            title: form.dataset.title || 'Are you sure?',
            text: form.dataset.confirm || 'This action cannot be undone.',
            type: form.dataset.type || 'warning',
            showCancelButton: true,
            confirmButtonColor: form.dataset.color === '#1ab394' ? window.tmsAccent() : (form.dataset.color || '#DD6B55'),
            confirmButtonText: form.dataset.button || 'Yes, continue',
            closeOnConfirm: true
        }, function () {
            form.dataset.confirmed = '1';
            form.submit();
        });
    });

    // Generic create/edit modal:
    //  <button data-toggle="modal" data-target="#m" data-action="url" data-method="PUT"
    //          data-title="Edit" data-values='{"name":"x"}'>
    $(document).on('show.bs.modal', '.js-form-modal', function (e) {
        var $btn = $(e.relatedTarget);
        if (!$btn.length) { return; }
        var $modal = $(this);
        var $form = $modal.find('form');
        $form[0].reset();
        $form.find('input[type=hidden][name=_method]').remove();
        $form.attr('action', $btn.data('action'));
        var method = ($btn.data('method') || 'POST').toUpperCase();
        if (method !== 'POST') {
            $form.append('<input type="hidden" name="_method" value="' + method + '">');
        }
        $modal.find('.modal-title').text($btn.data('title') || $modal.find('.modal-title').data('default'));
        var values = $btn.data('values') || {};
        $.each(values, function (name, value) {
            var $field = $form.find('[name="' + name + '"]');
            if ($field.is(':checkbox')) { $field.prop('checked', !!value); }
            else if ($field.attr('type') !== 'file') { $field.val(value === null ? '' : value); }
        });
        $modal.find('[data-hint]').each(function () {
            $(this).text(values[$(this).data('hint')] || '');
        });
    });

    // Judge screens: reload by themselves when the admin changes settings/logos or presses
    // "Refresh judges' screens". Pending scores are saved first (they are also kept in the browser).
    if (window.TMS_LIVE) {
        var live = window.TMS_LIVE, reloading = false;
        var safeReload = function () {
            reloading = true;
            toastr.info('The administrator updated this screen. Reloading…', '', {timeOut: 3000});
            var tries = 0;
            (function attempt() {
                var sheet = window.tmsSheet;
                if (sheet && sheet.busy() && tries++ < 12) {
                    sheet.flush();
                    return setTimeout(attempt, 700);
                }
                window.tmsReloading = true;
                setTimeout(function () { location.reload(); }, 600);
            })();
        };
        $('body').toggleClass('is-screen-locked', !!live.locked);
        setInterval(function () {
            if (reloading) { return; }
            $.ajax({url: live.url, cache: false, dataType: 'json', timeout: 5000}).done(function (res) {
                if (!res) { return; }
                if (typeof res.locked === 'boolean' && res.locked !== live.locked) {
                    live.locked = res.locked;
                    $('#screen-lock').toggleClass('show', res.locked);
                    $('body').toggleClass('is-screen-locked', res.locked);
                    if (res.locked && document.activeElement) { document.activeElement.blur(); }
                    if (!res.locked && window.tmsSheet) { window.tmsSheet.flush(); }   // send anything typed before the lock
                }
                if (res.token && res.token !== live.token) { safeReload(); }
            });
        }, 3000);
    }

    // ---- Notification bell: rings when judges log in, start scoring, submit... ----
    var $bell = $('#notif-bell');
    if ($bell.length) {
        var lastId = null, soundOn = true, audioCtx = null;
        try { soundOn = localStorage.getItem('tms-bell-sound') !== 'off'; } catch (e) {}
        var paintSound = function () { $bell.find('.notif-sound i').attr('class', 'fa ' + (soundOn ? 'fa-volume-up' : 'fa-volume-off')); };
        paintSound();

        var chime = function () {
            if (!soundOn) { return; }
            try {
                audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
                [[880, 0], [1320, 0.16]].forEach(function (n) {
                    var o = audioCtx.createOscillator(), g = audioCtx.createGain(), t = audioCtx.currentTime + n[1];
                    o.type = 'sine'; o.frequency.value = n[0];
                    g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.25, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.35);
                    o.connect(g); g.connect(audioCtx.destination); o.start(t); o.stop(t + 0.4);
                });
            } catch (e) { /* audio blocked until the user interacts with the page */ }
        };
        var esc = function (s) { return $('<div>').text(s == null ? '' : s).html(); };
        var render = function (res) {
            var $count = $bell.find('.notif-count');
            $count.text(res.unread > 99 ? '99+' : res.unread).toggle(res.unread > 0);
            if (!res.items.length) { return; }
            $bell.find('.notif-list').html(res.items.map(function (n) {
                return '<div class="notif-item' + (n.unread ? ' is-unread' : '') + '"><i class="fa ' + n.icon + ' ' + n.tone + ' fa-fw"></i>' +
                    '<div><b>' + esc(n.who) + '</b> ' + esc(n.text) + (n.event ? '<small class="d-block text-muted">' + esc(n.event) + '</small>' : '') +
                    '<small class="d-block text-muted">' + esc(n.ago) + '</small></div></div>';
            }).join(''));
        };
        var poll = function () {
            $.ajax({url: $bell.data('feed'), cache: false, dataType: 'json', timeout: 6000}).done(function (res) {
                render(res);
                if (lastId !== null && res.latest_id > lastId) {
                    var fresh = res.items.filter(function (n) { return n.id > lastId; });
                    $bell.removeClass('is-ringing'); void $bell[0].offsetWidth; $bell.addClass('is-ringing');
                    chime();
                    fresh.slice(0, 3).reverse().forEach(function (n) { toastr.info(n.who + ' ' + n.text + (n.event ? ' · ' + n.event : ''), 'Judge activity', {timeOut: 6000}); });
                }
                lastId = Math.max(lastId || 0, res.latest_id);
            });
        };
        poll();
        setInterval(poll, 5000);
        $bell.on('show.bs.dropdown', function () {
            $.post($bell.data('read')).done(function () { $bell.find('.notif-count').hide(); $bell.find('.notif-item').removeClass('is-unread'); });
        });
        $bell.on('click', '.notif-sound', function (e) {
            e.stopPropagation();
            soundOn = !soundOn;
            try { localStorage.setItem('tms-bell-sound', soundOn ? 'on' : 'off'); } catch (err) {}
            paintSound();
            if (soundOn) { chime(); }
        });
    }

    // Chart tooltips: any element with data-tip (works on hover and keyboard focus).
    var $tip = $('<div class="viz-tooltip" role="tooltip"></div>').appendTo('body').hide();
    function showTip(el, x, y) {
        $tip.text(el.getAttribute('data-tip')).show();
        var w = $tip.outerWidth(), h = $tip.outerHeight();
        var left = Math.min(x + 14, window.innerWidth - w - 8), top = y - h - 12;
        if (top < 8) { top = y + 16; }
        $tip.css({left: left, top: top});
    }
    $(document).on('mousemove', '[data-tip]', function (e) { showTip(this, e.clientX, e.clientY); });
    $(document).on('focus', '[data-tip]', function () {
        var r = this.getBoundingClientRect();
        showTip(this, r.left + r.width / 2, r.top);
    });
    $(document).on('mouseleave blur', '[data-tip]', function () { $tip.hide(); });
    $(document).on('refreshed', function () { $tip.hide(); });

    // Print only the generated login slips.
    $(document).on('click', '.js-print-slips', function () {
        $('body').addClass('printing-slips');
        window.print();
        setTimeout(function () { $('body').removeClass('printing-slips'); }, 500);
    });

    // Checkbox tiles
    $(document).on('change', '.check-tile input[type=checkbox]', function () {
        $(this).closest('.check-tile').toggleClass('checked', this.checked);
    });
    $('.check-tile input[type=checkbox]').trigger('change');

    // Live panels.
    //  <div data-refresh-url="..." data-pulse-url="..." data-pulse-version="...">
    //  polls the tiny pulse endpoint every 1.5 s and re-renders only when a judge saved,
    //  cleared or submitted something. A full refresh also runs every 30 s as a safety net.
    //  Without data-pulse-url it falls back to plain polling every data-refresh-seconds.
    $('[data-refresh-url]').each(function () {
        var $panel = $(this);
        var $toggle = $($panel.data('refresh-toggle'));
        var pulseUrl = $panel.data('pulse-url');
        var version = String($panel.data('pulse-version') || '');
        var busy = false, lastRender = Date.now();
        var $dot = $('.live-dot');

        function enabled() { return !$toggle.length || $toggle.is(':checked'); }

        function render() {
            if (!$panel.data('refresh-url')) { return $.Deferred().resolve(); }
            return $.ajax({url: $panel.data('refresh-url'), cache: false}).done(function (html) {
                $panel.html(html);
                lastRender = Date.now();
                $panel.trigger('refreshed');
            });
        }

        if (!pulseUrl) {
            setInterval(function () { if (enabled()) { render(); } },
                parseInt($panel.data('refresh-seconds') || 10, 10) * 1000);
            return;
        }

        setInterval(function () {
            if (busy || !enabled()) { return; }
            busy = true;
            $.ajax({url: pulseUrl, cache: false, dataType: 'json', timeout: 5000}).then(function (res) {
                $dot.removeClass('is-offline');
                if (res.version !== version || Date.now() - lastRender > 30000) {
                    version = res.version;
                    return render();
                }
            }, function () {
                $dot.addClass('is-offline');
            }).always(function () { busy = false; });
        }, 1500);
    });

    // DataTables for simple list tables
    $('.js-datatable').each(function () {
        $(this).DataTable({pageLength: 25, responsive: true, order: [], dom: '<"html5buttons"B>lTfgitp', buttons: []});
    });
})(jQuery);
