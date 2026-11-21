<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Round;
use App\Models\RoundTitle;
use Illuminate\Http\Request;

/** Placement titles for a round (pageants: Winner, 1st Runner-Up, ... for the Top N). */
class RoundTitleController extends Controller
{
    public function edit(Round $round)
    {
        $round->load(['event', 'titles', 'contestants']);
        $event = $round->event;
        $divisions = $round->contestants->pluck('division')->filter()->unique()->sort()->values()->all();
        $places = max(5, $round->titles->max('rank') ?? 0);

        return view('admin.events.titles', compact('round', 'event', 'divisions', 'places'));
    }

    public function update(Request $request, Round $round)
    {
        $data = $request->validate([
            'titles' => ['array'],
            'titles.*' => ['array'],
            'titles.*.*' => ['nullable', 'string', 'max:120'],
        ]);

        $round->titles()->delete();
        $count = 0;
        foreach ($data['titles'] ?? [] as $rank => $byDivision) {
            $rank = (int) $rank;
            if ($rank < 1 || $rank > 100) {
                continue;
            }
            foreach ($byDivision as $division => $title) {
                $title = trim((string) $title);
                if ($title === '') {
                    continue;
                }
                RoundTitle::create([
                    'round_id' => $round->id,
                    'rank' => $rank,
                    'division' => $division === '_all' ? null : $division,
                    'title' => $title,
                ]);
                $count++;
            }
        }
        ActivityLog::record('round', 'Saved '.$count.' placement title(s) for '.$round->name.'.', $round->event);

        return redirect()->route('admin.rounds.titles', $round)->with('success', 'Placement titles saved. They show on the results, the Top N view, print, PDF and Excel.');
    }
}
