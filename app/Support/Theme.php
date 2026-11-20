<?php

namespace App\Support;

use App\Models\Setting;

/**
 * System-wide highlight (accent) colour. Everything that used Inspinia's green now reads
 * CSS variables printed by Theme::styleTag(); readable text colours are derived automatically.
 */
class Theme
{
    public const DEFAULT = '#1ab394';

    public const PRESETS = [
        '#1ab394' => 'Teal green (default)',
        '#1e7b3c' => 'Forest green',
        '#1c84c6' => 'Sky blue',
        '#2a5bd7' => 'Royal blue',
        '#23395d' => 'Navy',
        '#6f42c1' => 'Purple',
        '#a4243b' => 'Maroon',
        '#d9363e' => 'Red',
        '#d6336c' => 'Pink',
        '#e8710a' => 'Orange',
        '#d4a106' => 'Gold',
        '#5f6b7a' => 'Slate gray',
    ];

    public static function accent(): string
    {
        $value = strtolower((string) Setting::get('theme.accent', self::DEFAULT));

        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : self::DEFAULT;
    }

    public const LIGHT_SURFACE = '#ffffff';
    public const DARK_SURFACE = '#1e2229';

    /**
     * CSS custom properties for a given accent colour.
     *  --accent          the colour itself (borders, bars, dots, focus rings)
     *  --accent-fill     background for buttons, badges and *selected* items: the closest shade whose
     *  --accent-on       text colour reads at >= 4.5:1, and (dark mode) that stands out from the page (>= 3:1)
     *  --accent-ink      the accent used as text on the page background
     */
    public static function vars(?string $hex = null): array
    {
        $hex = $hex ?? static::accent();
        [$r, $g, $b] = static::rgb($hex);
        $light = static::fill($hex, self::LIGHT_SURFACE, 1.3);
        $dark = static::fill($hex, self::DARK_SURFACE, 3.0);

        return [
            'light' => [
                '--accent' => $hex,
                '--accent-rgb' => "$r, $g, $b",
                '--accent-fill' => $light['fill'],
                '--accent-on' => $light['on'],
                '--accent-hover' => static::shade($light['fill'], -0.10),
                '--accent-active' => static::shade($light['fill'], -0.18),
                '--accent-contrast' => $light['on'],
                '--accent-ink' => static::readable($hex, self::LIGHT_SURFACE, 4.5, -1),
            ],
            'dark' => [
                '--accent-fill' => $dark['fill'],
                '--accent-on' => $dark['on'],
                '--accent-hover' => static::shade($dark['fill'], $dark['on'] === '#ffffff' ? -0.10 : 0.10),
                '--accent-active' => static::shade($dark['fill'], $dark['on'] === '#ffffff' ? -0.18 : 0.18),
                '--accent-contrast' => $dark['on'],
                '--accent-ink' => static::readable($hex, self::DARK_SURFACE, 4.5, +1),
            ],
        ];
    }

    /**
     * The shade of $hex nearest to the original that carries readable text (>= 4.5:1) and is
     * at least $surfaceMin:1 against the page surface. White text is preferred (it is how the
     * theme looks by default); dark text is used when white would need a big colour change (e.g. gold).
     *
     * @return array{fill: string, on: string}
     */
    public static function fill(string $hex, string $surface, float $surfaceMin): array
    {
        $best = null;
        foreach (['#ffffff' => 0, '#16181b' => 3] as $ink => $penalty) {
            for ($k = 0; $k <= 10; $k++) {
                foreach ($k === 0 ? [0] : [-$k, $k] as $step) {
                    $candidate = static::shade($hex, $step * 0.06);
                    if (static::contrast($ink, $candidate) >= 4.5 && static::contrast($candidate, $surface) >= $surfaceMin) {
                        $cost = $k + $penalty;
                        if (! $best || $cost < $best['cost']) {
                            $best = ['fill' => $candidate, 'on' => $ink, 'cost' => $cost];
                        }
                        break 2;
                    }
                }
            }
        }

        return $best ? ['fill' => $best['fill'], 'on' => $best['on']] : ['fill' => $hex, 'on' => '#ffffff'];
    }

    public static function styleTag(?string $hex = null): string
    {
        $vars = static::vars($hex);
        $line = fn (array $set) => collect($set)->map(fn ($v, $k) => "$k: $v;")->implode(' ');

        return '<style id="accent-vars">:root { '.$line($vars['light']).' } '
            .'html[data-theme="dark"] { '.$line($vars['dark']).' }</style>';
    }

    // ---------- colour maths ----------

    protected static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    protected static function hex(array $rgb): string
    {
        return '#'.implode('', array_map(fn ($c) => str_pad(dechex((int) round(max(0, min(255, $c)))), 2, '0', STR_PAD_LEFT), $rgb));
    }

    /** Mix towards black (amount < 0) or white (amount > 0). */
    public static function shade(string $hex, float $amount): string
    {
        $target = $amount < 0 ? 0 : 255;
        $t = abs($amount);

        return static::hex(array_map(fn ($c) => $c + ($target - $c) * $t, static::rgb($hex)));
    }

    public static function luminance(string $hex): float
    {
        $lin = array_map(function ($c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, static::rgb($hex));

        return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $l1 = static::luminance($a);
        $l2 = static::luminance($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /** Step the colour darker (-1) or lighter (+1) until it reaches the contrast target. */
    protected static function readable(string $hex, string $surface, float $target, int $direction): string
    {
        $color = $hex;
        for ($i = 0; $i < 20 && static::contrast($color, $surface) < $target; $i++) {
            $color = static::shade($color, 0.08 * $direction);
        }

        return $color;
    }
}
