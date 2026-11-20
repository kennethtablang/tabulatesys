<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class Logo extends Model
{
    public const SIZES = ['small' => 'Small (48px)', 'medium' => 'Medium (72px)', 'large' => 'Large (110px)'];

    public const TYPES = ['school' => 'School', 'organization' => 'Organization', 'lgu' => 'LGU', 'sponsor' => 'Sponsor', 'other' => 'Other'];

    protected $fillable = ['name', 'type', 'file', 'show_on_all', 'sort_order', 'scale'];

    /** Height of the logos on the right of the page heading (Settings → Logos → slider). */
    public static function headingHeight(): int
    {
        return max(40, min(200, (int) Setting::get('logos.heading_height', 96)));
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Other';
    }

    protected function casts(): array
    {
        return ['show_on_all' => 'boolean', 'sort_order' => 'integer', 'scale' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleted(fn (Logo $logo) => File::delete(public_path('uploads/'.$logo->file)));
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_logo');
    }

    public function url(): string
    {
        return asset('uploads/'.$this->file);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Logos marked "show on every event". */
    public static function global()
    {
        return static::where('show_on_all', true)->ordered()->get();
    }
}
