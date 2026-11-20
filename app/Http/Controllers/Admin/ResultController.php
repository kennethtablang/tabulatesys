<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Round;
use App\Services\TabulationService;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public const CHARTS = ['standings', 'breakdown', 'judges', 'categories', 'podium'];

    public function index(Request $request, Event $event, TabulationService $tabulation)
    {
        $this->authorizeEvent($event);
        $event->load('rounds');
        $round = $event->rounds->firstWhere('id', (int) $request->query('round')) ?? $event->rounds->last();
        $results = $round ? $tabulation->roundResults($round) : null;
        $version = $tabulation->version($event);

        // Table, or one of the chart views (remembered per viewer in the browser).
        $chart = in_array($request->query('view'), self::CHARTS, true) ? $request->query('view') : 'table';

        if ($request->ajax()) {
            return $chart === 'table'
                ? view('admin.results._tables', compact('event', 'round', 'results'))
                : view('admin.results._charts', compact('event', 'round', 'results', 'chart'));
        }

        return view('admin.results.index', compact('event', 'round', 'results', 'version', 'chart'));
    }

    public function monitor(Request $request, Event $event, TabulationService $tabulation)
    {
        $this->authorizeEvent($event);
        $event->load(['rounds.categories.criteria', 'rounds.contestants', 'judges']);
        $progress = $tabulation->progress($event);
        $version = $tabulation->version($event);

        if ($request->ajax()) {
            return view('admin.results._monitor', compact('event', 'progress'));
        }

        return view('admin.results.monitor', compact('event', 'progress', 'version'));
    }

    /** Reload every judge's screen (or only one event's judges) within a few seconds. */
    public function refreshJudges(Request $request)
    {
        $event = $request->filled('event') ? Event::findOrFail($request->integer('event')) : null;
        if ($event) {
            $this->authorizeEvent($event);
        } else {
            abort_unless($request->user()->isAdmin(), 403);
        }

        \App\Support\LiveDisplay::refresh($event);
        \App\Models\ActivityLog::record('settings', 'Refreshed the judges\' screens'.($event ? '' : ' (all events)').'.', $event);

        return back()->with('success', 'Judges\' screens will reload within a few seconds. Unsaved scores are saved first.');
    }

    /** Polled every couple of seconds by live screens; tiny response, no rendering. */
    public function pulse(Event $event, TabulationService $tabulation)
    {
        $this->authorizeEvent($event);

        return response()->json(['version' => $tabulation->version($event)])
            ->header('Cache-Control', 'no-store');
    }

    public function print(Request $request, Round $round, TabulationService $tabulation)
    {
        $round->load('event');
        $event = $round->event;
        $this->authorizeEvent($event);
        $results = $tabulation->roundResults($round);
        $detailed = $request->boolean('detailed');

        return view('admin.results.print', compact('event', 'round', 'results', 'detailed'));
    }
}
