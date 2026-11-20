<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Round;
use App\Services\TabulationService;
use Illuminate\Http\Request;

class RoundController extends Controller
{
    public function index(Event $event)
    {
        $event->load(['rounds.categories.criteria', 'rounds.contestants'])->loadCount('contestants');

        return view('admin.events.setup', compact('event'));
    }

    public function store(Request $request, Event $event)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sequence' => ['nullable', 'integer', 'min:1'],
            'carry_over' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $data['carry_over'] = (float) ($data['carry_over'] ?? 0);
        $isFirst = ! $event->rounds()->exists();
        $data['sequence'] ??= ((int) $event->rounds()->max('sequence')) + 1;

        $round = $event->rounds()->create($data);

        // The first round includes every contestant; later rounds are filled by advancing finalists.
        if ($isFirst) {
            $round->contestants()->sync($event->contestants()->pluck('id'));
        }

        return back()->with('success', 'Round added.');
    }

    public function update(Request $request, Round $round)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sequence' => ['required', 'integer', 'min:1'],
            'carry_over' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $data['carry_over'] = (float) ($data['carry_over'] ?? 0);
        $round->update($data);

        return back()->with('success', 'Round updated.');
    }

    public function destroy(Round $round)
    {
        if ($round->event->rounds()->count() <= 1) {
            return back()->with('error', 'An event needs at least one round.');
        }
        $round->delete();

        return back()->with('success', 'Round deleted.');
    }

    public function participants(Round $round, TabulationService $tabulation)
    {
        $round->load('event', 'contestants');
        $event = $round->event;
        $contestants = $event->contestants()->get();
        $previous = $round->previous();
        $previousResults = $previous ? $tabulation->roundResults($previous) : null;

        return view('admin.events.participants', compact('round', 'event', 'contestants', 'previous', 'previousResults'));
    }

    public function saveParticipants(Request $request, Round $round)
    {
        $ids = $round->event->contestants()->whereIn('id', (array) $request->input('contestants', []))->pluck('id');
        $round->contestants()->sync($ids);

        return redirect()->route('admin.events.setup', $round->event_id)
            ->with('success', $ids->count().' contestant(s) set for '.$round->name.'.');
    }

    public function advance(Request $request, Round $round, TabulationService $tabulation)
    {
        $data = $request->validate(['top' => ['required', 'integer', 'min:1']]);
        $previous = $round->previous();
        if (! $previous) {
            return back()->with('error', 'There is no previous round to advance contestants from.');
        }

        $ids = $tabulation->topContestantIds($previous, $data['top']);
        $round->contestants()->sync($ids);

        return redirect()->route('admin.events.setup', $round->event_id)
            ->with('success', count($ids).' contestant(s) advanced from '.$previous->name.' to '.$round->name.'.');
    }
}
