<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contestant;
use App\Models\Event;
use App\Models\Score;
use App\Models\User;
use App\Services\TabulationService;

class DashboardController extends Controller
{
    public function index(TabulationService $tabulation)
    {
        $stats = [
            'events' => Event::count(),
            'ongoing' => Event::where('status', 'ongoing')->count(),
            'contestants' => Contestant::count(),
            'judges' => User::judges()->count(),
            'scores' => Score::count(),
        ];

        $ongoing = Event::where('status', 'ongoing')
            ->with(['rounds.categories.criteria', 'rounds.contestants', 'judges'])
            ->get()
            ->map(function (Event $event) use ($tabulation) {
                $event->progress = $tabulation->progress($event);

                return $event;
            });

        $recent = Event::latest()->take(5)->get();

        return view('admin.dashboard', compact('stats', 'ongoing', 'recent'));
    }
}
