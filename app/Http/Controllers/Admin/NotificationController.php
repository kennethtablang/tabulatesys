<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Notification bell: a live feed of what the judges are doing (log in/out, start scoring,
 * submit final scores) plus failed login attempts. Tabulators only see their own event's judges.
 */
class NotificationController extends Controller
{
    public const ACTIONS = ['login', 'logout', 'scoring', 'submission', 'login_failed'];

    public const ICONS = [
        'login' => ['fa-sign-in', 'text-navy'],
        'logout' => ['fa-sign-out', 'text-muted'],
        'scoring' => ['fa-pencil', 'text-info'],
        'submission' => ['fa-check-circle', 'text-navy'],
        'login_failed' => ['fa-exclamation-triangle', 'text-danger'],
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $items = $this->query($user)->latest('id')->limit(15)->get();
        $unread = $this->query($user)->where('id', '>', $user->notifications_read_id)->count();

        return response()->json([
            'unread' => $unread,
            'latest_id' => (int) ($items->first()->id ?? 0),
            'items' => $items->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'icon' => self::ICONS[$log->action][0] ?? 'fa-info-circle',
                'tone' => self::ICONS[$log->action][1] ?? 'text-muted',
                'who' => $log->user_name ?? 'Someone',
                'text' => $this->sentence($log),
                'event' => $log->event?->name,
                'ago' => $log->created_at->diffForHumans(null, true, true).' ago',
                'unread' => $log->id > $user->notifications_read_id,
            ])->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function read(Request $request)
    {
        $user = $request->user();
        $latest = (int) $this->query($user)->max('id');
        $user->forceFill(['notifications_read_id' => max($latest, $user->notifications_read_id)])->save();

        return response()->json(['unread' => 0]);
    }

    /** Judge activity (and failed logins) visible to this admin / tabulator. */
    protected function query(User $user)
    {
        $query = ActivityLog::with('event:id,name')
            ->whereIn('action', self::ACTIONS)
            ->where(function ($q) {
                $q->whereIn('user_id', User::judges()->select('id'))->orWhere('action', 'login_failed');
            });

        if ($user->isTabulator() && $user->event_id) {
            $judgeIds = User::judges()->whereHas('judgedEvents', fn ($e) => $e->whereKey($user->event_id))->pluck('id');
            $query->where(fn ($q) => $q->where('event_id', $user->event_id)
                ->orWhere(fn ($q) => $q->whereNull('event_id')->whereIn('user_id', $judgeIds)));
        }

        return $query;
    }

    protected function sentence(ActivityLog $log): string
    {
        return match ($log->action) {
            'login' => 'logged in',
            'logout' => 'logged out',
            'login_failed' => mb_strtolower(rtrim($log->description, '.')),
            default => lcfirst(rtrim($log->description, '.')),
        };
    }
}
