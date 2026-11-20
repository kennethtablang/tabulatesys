<?php

namespace App\Http\Controllers\Tabulator;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\TabulationService;

class DashboardController extends Controller
{
    public function index(TabulationService $tabulation)
    {
        $user = auth()->user();
        // A tabulator generated for one event always sees it (with its Active/Inactive state);
        // a general tabulator sees every active event.
        $events = Event::visibleTo($user)
            ->when(! $user->event_id, fn ($q) => $q->where('status', 'ongoing'))
            ->with(['rounds.categories.criteria', 'rounds.contestants', 'judges'])
            ->orderBy('name')
            ->get()
            ->map(function (Event $event) use ($tabulation) {
                $event->progress = $tabulation->progress($event);

                return $event;
            });

        return view('tabulator.dashboard', compact('events'));
    }
}
