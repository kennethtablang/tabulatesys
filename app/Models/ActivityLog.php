<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public const ACTIONS = [
        'login' => 'Logged in',
        'login_failed' => 'Failed login',
        'logout' => 'Logged out',
        'event' => 'Event',
        'tabulation' => 'Tabulation on/off',
        'category' => 'Category',
        'submission' => 'Score sheet',
        'account' => 'Accounts',
        'round' => 'Round',
        'settings' => 'Settings',
        'lock' => 'Screen lock',
        'scoring' => 'Started scoring',
    ];

    protected $fillable = ['user_id', 'event_id', 'user_name', 'action', 'description', 'ip_address'];

    public function user() { return $this->belongsTo(User::class); }
    public function event() { return $this->belongsTo(Event::class); }

    public static function record(string $action, string $description, ?Event $event = null, ?User $user = null): void
    {
        $user ??= auth()->user();

        static::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'event_id' => $event?->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()?->ip(),
        ]);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst($this->action);
    }
}
