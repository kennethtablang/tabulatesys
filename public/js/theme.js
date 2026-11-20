/*
 * Light / dark mode. Loaded in <head> (before the stylesheets apply) so the page never
 * flashes white. Uses the computer's own setting until the user picks one with a
 * [data-theme-toggle] button; that choice is remembered in this browser.
 */
(function () {
    'use strict';
    var KEY = 'tms-theme';
    var root = document.documentElement;
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function saved() {
        try { return localStorage.getItem(KEY); } catch (e) { return null; }
    }
    function system() {
        return media && media.matches ? 'dark' : 'light';
    }
    function apply(theme) {
        root.setAttribute('data-theme', theme);
        var buttons = document.querySelectorAll('[data-theme-toggle]');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        }
    }

    apply(saved() || system());

    // Follow the computer's setting live, unless the user picked a theme here.
    if (media) {
        var onChange = function () { if (!saved()) { apply(system()); } };
        if (media.addEventListener) { media.addEventListener('change', onChange); } else if (media.addListener) { media.addListener(onChange); }
    }

    document.addEventListener('DOMContentLoaded', function () { apply(root.getAttribute('data-theme')); });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-theme-toggle]') : null;
        if (!btn) { return; }
        e.preventDefault();
        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        apply(next);
        try { localStorage.setItem(KEY, next); } catch (err) { /* private mode: still switches for this page */ }
    });
})();
