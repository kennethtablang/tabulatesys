<?php

namespace App\Http\Controllers\Judge;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\TabulationService;

class DashboardController extends Controller
{
    public function index()
    {
        $events = auth()->user()->judgedEvents()
            ->whereIn('status', ['ongoing', 'completed'])
            ->with(['rounds.categories'])
            ->orderByRaw("CASE status WHEN 'ongoing' THEN 0 ELSE 1 END")
            ->latest('event_date')
            ->get();

        return view('judge.dashboard', compact('events'));
    }

    /** Polled every few seconds by judge screens; tells them when to reload. */
    public function ping(\Illuminate\Http\Request $request)
    {
        $judge = $request->user();
        $eventId = $request->integer('event') ?: null;
        if ($eventId && ! $judge->judgedEvents()->whereKey($eventId)->exists()) {
            $eventId = null;
        }
        \App\Support\LiveDisplay::markSeen($judge, $eventId, mb_substr((string) $request->query('page', ''), 0, 60));

        return response()->json([
            'token' => \App\Support\LiveDisplay::token(\App\Support\LiveDisplay::eventIdsFor($judge, $eventId), $judge),
            'locked' => \App\Support\ScreenLock::isLocked($judge, $eventId),
        ])
            ->header('Cache-Control', 'no-store');
    }

    public function event(Event $event, TabulationService $tabulation)
    {
        abort_unless($event->judges()->whereKey(auth()->id())->exists(), 403, 'You are not a judge of this event.');

        $event->load(['rounds.categories.criteria', 'rounds.contestants', 'judges']);
        $progress = $tabulation->progress($event);

        return view('judge.event', compact('event', 'progress'));
    }
}
