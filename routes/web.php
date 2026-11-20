<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Judge;
use App\Http\Controllers\Tabulator;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', fn () => redirect()->route(auth()->user()->homeRoute()))->name('home');

    // ---------------- Tabulator (and administrator): live scoring & results ----------------
    Route::prefix('admin')->name('admin.')->middleware('role:admin,tabulator')->group(function () {
        Route::patch('categories/{category}/status', [Admin\CategoryController::class, 'status'])->name('categories.status');
        Route::delete('categories/{category}/submissions/{judge}', [Admin\CategoryController::class, 'unlock'])->name('categories.unlock');

        Route::get('events/{event}/monitor', [Admin\ResultController::class, 'monitor'])->name('events.monitor');
        Route::get('events/{event}/results', [Admin\ResultController::class, 'index'])->name('events.results');
        Route::get('events/{event}/pulse', [Admin\ResultController::class, 'pulse'])->name('events.pulse');
        Route::post('judges/refresh', [Admin\ResultController::class, 'refreshJudges'])->name('judges.refresh');
        Route::post('judges/lock', [Admin\ScreenLockController::class, 'update'])->name('judges.lock');
        Route::get('notifications', [Admin\NotificationController::class, 'index'])->name('notifications');
        Route::post('notifications/read', [Admin\NotificationController::class, 'read'])->name('notifications.read');
        Route::get('rounds/{round}/print', [Admin\ResultController::class, 'print'])->name('rounds.print');
        Route::get('rounds/{round}/export/excel', [Admin\ExportController::class, 'excel'])->name('rounds.export.excel');
        Route::get('rounds/{round}/export/pdf', [Admin\ExportController::class, 'pdf'])->name('rounds.export.pdf');
    });

    Route::get('tabulator', [Tabulator\DashboardController::class, 'index'])
        ->middleware('role:tabulator')->name('tabulator.dashboard');

    // ---------------- Administrator ----------------
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::resource('users', Admin\UserController::class)->except('show');

        Route::resource('events', Admin\EventController::class);
        Route::patch('events/{event}/status', [Admin\EventController::class, 'status'])->name('events.status');

        // Event setup: rounds → categories → criteria
        Route::get('events/{event}/setup', [Admin\RoundController::class, 'index'])->name('events.setup');
        Route::post('events/{event}/rounds', [Admin\RoundController::class, 'store'])->name('rounds.store');
        Route::put('rounds/{round}', [Admin\RoundController::class, 'update'])->name('rounds.update');
        Route::delete('rounds/{round}', [Admin\RoundController::class, 'destroy'])->name('rounds.destroy');
        Route::get('rounds/{round}/participants', [Admin\RoundController::class, 'participants'])->name('rounds.participants');
        Route::put('rounds/{round}/participants', [Admin\RoundController::class, 'saveParticipants'])->name('rounds.participants.save');
        Route::post('rounds/{round}/advance', [Admin\RoundController::class, 'advance'])->name('rounds.advance');

        Route::post('rounds/{round}/categories', [Admin\CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::post('categories/{category}/criteria', [Admin\CriterionController::class, 'store'])->name('criteria.store');
        Route::put('criteria/{criterion}', [Admin\CriterionController::class, 'update'])->name('criteria.update');
        Route::delete('criteria/{criterion}', [Admin\CriterionController::class, 'destroy'])->name('criteria.destroy');

        // Contestants & judges
        Route::get('events/{event}/contestants', [Admin\ContestantController::class, 'index'])->name('contestants.index');
        Route::post('events/{event}/contestants', [Admin\ContestantController::class, 'store'])->name('contestants.store');
        Route::put('contestants/{contestant}', [Admin\ContestantController::class, 'update'])->name('contestants.update');
        Route::delete('contestants/{contestant}', [Admin\ContestantController::class, 'destroy'])->name('contestants.destroy');

        Route::get('events/{event}/judges', [Admin\EventJudgeController::class, 'index'])->name('events.judges');
        Route::put('events/{event}/judges', [Admin\EventJudgeController::class, 'update'])->name('events.judges.update');
        Route::post('events/{event}/judges/generate', [Admin\EventJudgeController::class, 'generate'])->name('events.judges.generate');
        Route::delete('events/{event}/judges/{user}', [Admin\EventJudgeController::class, 'detach'])->name('events.judges.detach');
        Route::post('events/{event}/tabulators', [Admin\EventJudgeController::class, 'generateTabulator'])->name('events.tabulators.store');
        Route::delete('events/{event}/tabulators/{user}', [Admin\EventJudgeController::class, 'destroyTabulator'])->name('events.tabulators.destroy');
        Route::post('users/{user}/reset-password', [Admin\EventJudgeController::class, 'resetPassword'])->name('users.reset-password');

        // Tabulation history: past events, score change log, activity log
        Route::get('history', [Admin\HistoryController::class, 'index'])->name('history');

        // Settings (general + judge score-sheet layout)
        Route::get('settings', [Admin\SettingsController::class, 'index'])->name('settings');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::get('settings/preview', [Admin\SettingsController::class, 'preview'])->name('settings.preview');

        // Logo library (school, LGU, sponsors...)
        Route::get('logos', [Admin\LogoController::class, 'index'])->name('logos.index');
        Route::post('logos', [Admin\LogoController::class, 'store'])->name('logos.store');
        Route::put('logos/options', [Admin\LogoController::class, 'options'])->name('logos.options');
        Route::put('logos/{logo}', [Admin\LogoController::class, 'update'])->name('logos.update');
        Route::delete('logos/{logo}', [Admin\LogoController::class, 'destroy'])->name('logos.destroy');
    });

    // ---------------- Judge ----------------
    Route::prefix('judge')->name('judge.')->middleware('role:judge')->group(function () {
        Route::get('/', [Judge\DashboardController::class, 'index'])->name('dashboard');
        Route::get('ping', [Judge\DashboardController::class, 'ping'])->name('ping');
        Route::get('events/{event}', [Judge\DashboardController::class, 'event'])->name('event');
        Route::get('categories/{category}', [Judge\ScoreController::class, 'sheet'])->name('sheet');
        Route::post('categories/{category}/scores', [Judge\ScoreController::class, 'save'])->name('scores.save');
        Route::post('categories/{category}/autosave', [Judge\ScoreController::class, 'autosave'])->name('scores.autosave');
        Route::post('categories/{category}/submit', [Judge\ScoreController::class, 'submit'])->name('scores.submit');
    });
});
