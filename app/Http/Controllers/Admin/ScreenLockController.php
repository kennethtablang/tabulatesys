<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\User;
use App\Support\ScreenLock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Lock / unlock judge screens: one judge, every judge of an event, or every judge. */
class ScreenLockController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['judge', 'event', 'all'])],
            'lock' => ['required', 'boolean'],
            'event' => ['nullable', 'integer', 'exists:events,id'],
            'judge' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $lock = (bool) $data['lock'];
        $verb = $lock ? 'Locked' : 'Unlocked';
        $event = isset($data['event']) ? Event::find($data['event']) : null;

        switch ($data['scope']) {
            case 'all':
                abort_unless($request->user()->isAdmin(), 403);
                ScreenLock::setAll($lock);
                ActivityLog::record('lock', $verb.' every judge\'s screen (all events).');
                $message = $lock ? 'Every judge\'s screen is now locked.' : 'Every judge\'s screen is unlocked.';
                break;

            case 'event':
                abort_unless($event, 422);
                $this->authorizeEvent($event);
                ScreenLock::setEvent($event, $lock);
                ActivityLog::record('lock', $verb.' the screens of all judges of this event.', $event);
                $message = $lock ? 'All judges of '.$event->name.' are now locked.' : 'All judges of '.$event->name.' are unlocked.';
                break;

            default:
                $judge = User::judges()->findOrFail($data['judge'] ?? 0);
                if (! $request->user()->isAdmin()) {
                    // A tabulator may only lock judges of their own event.
                    abort_unless($event && $event->judges()->whereKey($judge->id)->exists(), 403);
                    $this->authorizeEvent($event);
                }
                ScreenLock::setJudge($judge, $lock);
                ActivityLog::record('lock', $verb.' the screen of '.$judge->name.'.', $event);
                $message = $judge->name.'\'s screen is now '.($lock ? 'locked' : 'unlocked').'.';
        }

        return back()->with('success', $message.' It takes effect within a few seconds.');
    }
}
