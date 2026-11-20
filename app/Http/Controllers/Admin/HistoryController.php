<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\ScoreLog;
use App\Models\User;
use Illuminate\Http\Request;

/** Tabulation History: past events & final results, score change log, activity log. */
class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['events', 'scores', 'activity'], true) ? $request->query('tab') : 'events';
        $eventId = $request->integer('event') ?: null;
        $eventOptions = Event::orderBy('name')->get(['id', 'name', 'status']);

        $data = compact('tab', 'eventId', 'eventOptions');

        if ($tab === 'events') {
            $data['events'] = Event::withCount(['contestants', 'judges', 'scores'])
                ->with('rounds')
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->orderByRaw("CASE status WHEN 'completed' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
                ->latest('event_date')
                ->paginate(20)->withQueryString();
        }

        if ($tab === 'scores') {
            $data['judgeOptions'] = User::judges()->orderBy('name')->get(['id', 'name', 'username']);
            $data['logs'] = ScoreLog::with([
                'event:id,name', 'category:id,name', 'criterion:id,name,max_score',
                'contestant:id,number,name,division', 'judge:id,name,username',
            ])
                ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
                ->when($request->integer('judge'), fn ($q, $j) => $q->where('judge_id', $j))
                ->latest('id')
                ->paginate(50)->withQueryString();
        }

        if ($tab === 'activity') {
            $data['actions'] = ActivityLog::ACTIONS;
            $data['logs'] = ActivityLog::with('event:id,name')
                ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
                ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
                ->latest('id')
                ->paginate(50)->withQueryString();
        }

        return view('admin.history.index', $data);
    }
}
