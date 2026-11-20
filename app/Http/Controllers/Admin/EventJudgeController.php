<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\User;
use App\Services\AccountGenerator;
use Illuminate\Http\Request;

/** Event → Judges & Tabulator: create the people who score and run this event. */
class EventJudgeController extends Controller
{
    public function index(Event $event)
    {
        $event->load(['judges', 'tabulators']);
        $judges = User::judges()->withCount('judgedEvents')->orderBy('name')->get();

        return view('admin.events.judges', compact('event', 'judges'));
    }

    /** Pick from existing judge accounts. */
    public function update(Request $request, Event $event)
    {
        $ids = User::judges()->whereIn('id', (array) $request->input('judges', []))->pluck('id');
        $event->judges()->sync($ids);
        ActivityLog::record('account', $ids->count().' judge(s) assigned to the event.', $event);

        return back()->with('success', $ids->count().' judge(s) assigned to '.$event->name.'.');
    }

    /** Create new judge accounts (one per name) for this event with generated logins. */
    public function generate(Request $request, Event $event, AccountGenerator $accounts)
    {
        $data = $request->validate(['names' => ['required', 'string', 'max:2000']]);
        $names = collect(preg_split('/\r\n|\r|\n/', $data['names']))
            ->map(fn ($n) => trim($n))->filter()->unique()->take(30)->values();

        if ($names->isEmpty()) {
            return back()->with('error', 'Type at least one judge name.');
        }

        $slips = $names->map(fn ($name) => $accounts->judge($event, mb_substr($name, 0, 255)))->all();
        ActivityLog::record('account', 'Generated '.count($slips).' judge account(s): '.collect($slips)->pluck('username')->join(', ').'.', $event);

        return back()->with('credentials', $slips)
            ->with('success', count($slips).' judge account(s) created. Print or write down the login slips now.');
    }

    public function detach(Event $event, User $user)
    {
        $event->judges()->detach($user->id);
        ActivityLog::record('account', 'Removed judge '.$user->name.' ('.$user->username.') from the event.', $event);

        return back()->with('success', $user->name.' was removed from '.$event->name.'. Their account still exists.');
    }

    public function generateTabulator(Request $request, Event $event, AccountGenerator $accounts)
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:255']]);
        $slip = $accounts->tabulator($event, $data['name'] ?? null);
        ActivityLog::record('account', 'Generated tabulator account '.$slip['username'].'.', $event);

        return back()->with('credentials', [$slip])
            ->with('success', 'Tabulator account created. Print or write down the login slip now.');
    }

    public function destroyTabulator(Event $event, User $user)
    {
        abort_unless($user->isTabulator() && $user->event_id === $event->id, 404);
        $user->delete();
        ActivityLog::record('account', 'Deleted tabulator account '.$user->username.'.', $event);

        return back()->with('success', 'Tabulator account '.$user->username.' deleted.');
    }

    /** New random password for a judge/tabulator; shown once on a login slip. */
    public function resetPassword(User $user, AccountGenerator $accounts)
    {
        abort_if($user->isAdmin(), 403, 'Change administrator passwords from the account form.');
        $slip = $accounts->resetPassword($user);
        ActivityLog::record('account', 'Reset the password of '.$user->name.' ('.$user->username.').', $user->event);

        return back()->with('credentials', [$slip])
            ->with('success', 'New password generated for '.$user->name.'.');
    }
}
