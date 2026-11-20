<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Keeps judges' screens in step with the admin without anyone pressing F5.
 *
 * Judge pages poll a tiny "ping" endpoint. Its token changes when
 *  - the admin changes anything judges see (settings, logos, an event's layout/logos) → touch()
 *  - the admin presses "Refresh judges' screens" (all, or one event)                   → refresh()
 * and the page then saves any pending scores and reloads itself.
 */
class LiveDisplay
{
    /** Something judges see changed: every judge screen reloads, and judges' own layout picks reset. */
    public static function touch(): void
    {
        Setting::put(['display.version' => (string) Str::ulid()]);
    }

    /** Force judge screens to reload: all of them, or only for one event. */
    public static function refresh(?Event $event = null): void
    {
        Setting::put([($event ? 'display.refresh.event.'.$event->id : 'display.refresh.all') => (string) Str::ulid()]);
    }

    /** Reload one judge's screen (e.g. right after their score sheet is unlocked). */
    public static function refreshJudge(User $judge): void
    {
        Setting::put(['display.refresh.user.'.$judge->id => (string) Str::ulid()]);
    }

    public static function version(): string
    {
        return (string) Setting::get('display.version', '0');
    }

    /** @param  int[]  $eventIds */
    public static function token(array $eventIds, ?User $judge = null): string
    {
        sort($eventIds);
        $parts = [static::version(), Setting::get('display.refresh.all', '0'), $judge ? Setting::get('display.refresh.user.'.$judge->id, '0') : '-'];
        foreach ($eventIds as $id) {
            $parts[] = $id.':'.Setting::get('display.refresh.event.'.$id, '0');
        }

        return substr(md5(implode('|', $parts)), 0, 16);
    }

    /** Event ids a judge's current page depends on. */
    public static function eventIdsFor(User $judge, ?int $eventId): array
    {
        return $eventId ? [$eventId] : $judge->judgedEvents()->pluck('events.id')->all();
    }

    public static function markSeen(User $judge, ?int $eventId, string $page): void
    {
        Cache::put('judge_seen.'.$judge->id, ['at' => time(), 'event' => $eventId, 'page' => $page], 120);
    }

    /** @return array{at:int, event:?int, page:string}|null  last ping from that judge (online if < 15 s ago) */
    public static function seen(User $judge): ?array
    {
        return Cache::get('judge_seen.'.$judge->id);
    }

    public static function isOnline(User $judge): bool
    {
        $seen = static::seen($judge);

        return $seen && time() - $seen['at'] <= 15;
    }
}
