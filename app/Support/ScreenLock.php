<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Setting;
use App\Models\User;

/**
 * "Lock judge screen": the admin/tabulator can freeze one judge's screen, every judge of an event,
 * or every judge in the system. A locked judge sees a full-screen notice and the server refuses
 * their score changes until the screen is unlocked. Stored as settings, read by the judges' ping.
 */
class ScreenLock
{
    public static function isLocked(User $judge, ?int $eventId = null): bool
    {
        return static::flag('lock.all')
            || static::flag('lock.user.'.$judge->id)
            || ($eventId && static::flag('lock.event.'.$eventId));
    }

    public static function allLocked(): bool
    {
        return static::flag('lock.all');
    }

    public static function eventLocked(Event $event): bool
    {
        return static::flag('lock.event.'.$event->id);
    }

    public static function judgeLocked(User $judge): bool
    {
        return static::flag('lock.user.'.$judge->id);
    }

    public static function setAll(bool $locked): void
    {
        Setting::put(['lock.all' => $locked]);
    }

    public static function setEvent(Event $event, bool $locked): void
    {
        Setting::put(['lock.event.'.$event->id => $locked]);
    }

    public static function setJudge(User $judge, bool $locked): void
    {
        Setting::put(['lock.user.'.$judge->id => $locked]);
    }

    public static function message(): string
    {
        return 'Your screen has been locked by the tabulation team. Please wait. Your scores are safe and you can continue once it is unlocked.';
    }

    protected static function flag(string $key): bool
    {
        return Setting::get($key, '0') === '1';
    }
}
