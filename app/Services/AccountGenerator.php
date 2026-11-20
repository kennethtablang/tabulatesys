<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;

/**
 * Creates judge / tabulator accounts with system-made usernames and passwords.
 * Passwords are only ever shown once (on the printable login slips); they are stored hashed.
 */
class AccountGenerator
{
    /** No look-alike characters (0/O, 1/l/I) so slips are easy to type. */
    protected const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /** @return array{name: string, username: string, password: string, role: string, event: string|null} */
    public function judge(Event $event, string $name): array
    {
        $password = static::password();
        $user = User::create([
            'name' => $name,
            'username' => $this->username($event, 'judge'),
            'password' => $password,
            'role' => User::ROLE_JUDGE,
            'is_active' => true,
        ]);
        $event->judges()->syncWithoutDetaching([$user->id]);

        return $this->slip($user, $password, $event);
    }

    /** @return array{name: string, username: string, password: string, role: string, event: string|null} */
    public function tabulator(Event $event, ?string $name = null): array
    {
        $password = static::password();
        $user = User::create([
            'name' => $name ?: 'Tabulator · '.$event->name,
            'username' => $this->username($event, 'tab'),
            'password' => $password,
            'role' => User::ROLE_TABULATOR,
            'event_id' => $event->id,
            'is_active' => true,
        ]);

        return $this->slip($user, $password, $event);
    }

    /** @return array{name: string, username: string, password: string, role: string, event: string|null} */
    public function resetPassword(User $user, ?Event $event = null): array
    {
        $password = static::password();
        $user->update(['password' => $password]);

        return $this->slip($user, $password, $event ?? $user->event);
    }

    public static function password(int $length = 8): string
    {
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $out;
    }

    /** e.g. ev3-judge1, ev3-judge2, ev3-tab1 */
    protected function username(Event $event, string $kind): string
    {
        $n = 1;
        while (User::where('username', $name = "ev{$event->id}-{$kind}{$n}")->exists()) {
            $n++;
        }

        return $name;
    }

    protected function slip(User $user, string $password, ?Event $event): array
    {
        return [
            'name' => $user->name,
            'username' => $user->username,
            'password' => $password,
            'role' => $user->roleLabel(),
            'event' => $event?->name,
        ];
    }
}
