<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Services\TabulationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $events = Event::withCount(['contestants', 'judges', 'rounds', 'categories'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByRaw("CASE status WHEN 'ongoing' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->latest('event_date')
            ->get();

        return view('admin.events.index', compact('events'));
    }

    public function create()
    {
        return view('admin.events.form', ['event' => new Event(['type' => 'pageant', 'scoring_method' => 'average', 'status' => 'draft'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $event = Event::create($data);

        // Every event starts with one round so categories can be added immediately.
        $event->rounds()->create(['name' => $request->input('first_round', 'Main Round') ?: 'Main Round', 'sequence' => 1]);
        $event->logos()->sync($this->logoIds($request));
        ActivityLog::record('event', 'Created event "'.$event->name.'".', $event);

        return redirect()->route('admin.events.setup', $event)
            ->with('success', 'Event created. Now add the categories and criteria.');
    }

    public function show(Event $event, TabulationService $tabulation)
    {
        $event->load(['rounds.categories.criteria', 'rounds.contestants', 'judges'])->loadCount('contestants');
        $progress = $tabulation->progress($event);

        return view('admin.events.show', compact('event', 'progress'));
    }

    public function edit(Event $event)
    {
        return view('admin.events.form', compact('event'));
    }

    public function update(Request $request, Event $event)
    {
        $event->update($this->validated($request));
        $event->logos()->sync($this->logoIds($request));
        \App\Support\LiveDisplay::touch();
        ActivityLog::record('event', 'Edited event details.', $event);

        return redirect()->route('admin.events.show', $event)->with('success', 'Event updated.');
    }

    public function status(Request $request, Event $event)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Event::STATUSES))]]);
        $event->update($data);
        ActivityLog::record('tabulation', 'Tabulation set to '.Event::STATUSES[$data['status']].'.', $event);
        \App\Support\LiveDisplay::refresh($event);   // judges see the event appear / disappear right away

        $message = match ($data['status']) {
            'ongoing' => 'Tabulation is now ACTIVE. Judges can score open categories.',
            'draft' => 'Tabulation is now INACTIVE. Judges can no longer score this event.',
            default => 'Event marked as completed. It is kept in Tabulation History.',
        };

        return back()->with('success', $message);
    }

    public function destroy(Event $event)
    {
        ActivityLog::record('event', 'Deleted event "'.$event->name.'".');
        $event->delete();

        return redirect()->route('admin.events.index')->with('success', 'Event deleted.');
    }

    protected function logoIds(Request $request): array
    {
        return \App\Models\Logo::where('show_on_all', false)
            ->whereIn('id', array_map('intval', (array) $request->input('logos', [])))
            ->pluck('id')->all();
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Event::TYPES))],
            'description' => ['nullable', 'string', 'max:2000'],
            'venue' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'scoring_method' => ['required', Rule::in(array_keys(Event::METHODS))],
            'status' => ['required', Rule::in(array_keys(Event::STATUSES))],
            'sheet_layout' => ['nullable', Rule::in(array_keys(\App\Support\JudgeDisplay::LAYOUTS))],
        ]);
    }
}
