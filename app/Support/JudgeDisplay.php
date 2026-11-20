<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Setting;
use Illuminate\Support\Collection;

/** How the judges' score sheet shows contestants: layout + display options (Settings → Judge Score Sheet). */
class JudgeDisplay
{
    public const LAYOUTS = [
        'table' => ['Score Table', 'fa-table', 'All contestants in rows and every criterion in columns. Best on a wide screen with a few criteria.'],
        'cards' => ['Contestant Cards', 'fa-th-large', 'A card for each contestant with a big photo and its own score boxes. Easy to read on any screen.'],
        'focus' => ['One at a Time', 'fa-user', 'One contestant fills the screen with Previous / Next buttons and a number strip. Best for tablets and close judging.'],
        'criteria' => ['By Criterion', 'fa-list-ol', 'Score one criterion for every contestant, then move to the next. Helps judges compare contestants side by side.'],
        'compact' => ['Compact List', 'fa-align-justify', 'A dense list without photos. Best for many contestants or small screens.'],
    ];

    public const ORDERS = ['number' => 'Contestant number', 'division' => 'Division, then number', 'name' => 'Name (A–Z)'];
    public const PHOTO_SIZES = ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'];
    public const INPUT_SIZES = ['normal' => 'Normal', 'large' => 'Large (touch screens)'];

    /** Setting key => default. Booleans are stored as '1' / '0'. */
    public const DEFAULTS = [
        'judge.sheet_layout' => 'table',
        'judge.allow_switch' => true,
        'judge.show_photos' => true,
        'judge.photo_size' => 'medium',
        'judge.show_names' => true,
        'judge.show_details' => true,
        'judge.show_totals' => true,
        'judge.show_my_rank' => false,
        'judge.contestant_order' => 'number',
        'judge.input_size' => 'normal',
    ];

    /** System-wide options with proper types. */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $value = Setting::get($key, $default);
            $out[substr($key, 6)] = is_bool($default) ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : (string) $value;
        }
        if (! isset(self::LAYOUTS[$out['sheet_layout']])) {
            $out['sheet_layout'] = 'table';
        }

        return $out;
    }

    /**
     * Options for one event's score sheet. Layout precedence:
     * the judge's own pick (if allowed) → the event's layout → the system default.
     */
    public static function forEvent(Event $event, ?string $requested = null): array
    {
        $options = static::defaults();
        $layout = isset(self::LAYOUTS[$event->sheet_layout ?? '']) ? $event->sheet_layout : $options['sheet_layout'];
        $options['default_layout'] = $layout;

        if ($options['allow_switch']) {
            $sessionKey = 'judge_layout.'.$event->id;
            $version = LiveDisplay::version();
            if ($requested !== null && isset(self::LAYOUTS[$requested])) {
                session([$sessionKey => ['layout' => $requested, 'version' => $version]]);
            }
            // A pick made before the admin last changed the settings no longer applies.
            $picked = session($sessionKey);
            if (is_array($picked) && ($picked['version'] ?? null) === $version && isset(self::LAYOUTS[$picked['layout'] ?? ''])) {
                $layout = $picked['layout'];
            }
        } elseif ($requested !== null && isset(self::LAYOUTS[$requested]) && auth()->user()?->isAdmin()) {
            $layout = $requested;   // admin previewing a layout
        }

        $options['layout'] = $layout;
        if ($layout === 'compact') {
            $options['show_photos'] = false;
        }

        return $options;
    }

    public static function sort(Collection $contestants, string $order): Collection
    {
        return match ($order) {
            'name' => $contestants->sortBy(fn ($c) => mb_strtolower($c->name))->values(),
            'division' => $contestants->sortBy(fn ($c) => sprintf('%s|%06d', $c->division ?? '', $c->number))->values(),
            default => $contestants->sortBy('number')->values(),
        };
    }
}
