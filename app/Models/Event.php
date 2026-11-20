<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    public const TYPES = [
        'pageant' => 'Pageant',
        'singing' => 'Singing Contest',
        'dance' => 'Dance Competition',
        'talent' => 'Talent Show',
        'debate' => 'Debate / Oratorical',
        'other' => 'Other',
    ];

    public const METHODS = [
        'average' => 'Weighted Average (highest score wins)',
        'ranking' => 'Rank Sum (lowest total rank wins)',
    ];

    /**
     * Tabulation status. Stored values are kept for compatibility:
     *  draft     = Inactive  (being set up, or paused: judges and tabulators can't score)
     *  ongoing   = Active    (judges can score open categories, live results run)
     *  completed = Completed (finished; kept in Tabulation History)
     */
    public const STATUSES = ['draft' => 'Inactive', 'ongoing' => 'Active', 'completed' => 'Completed'];

    protected $fillable = ['name', 'type', 'description', 'venue', 'event_date', 'scoring_method', 'status', 'sheet_layout'];

    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }

    protected static function booted(): void
    {
        // Accounts generated for this event only (its tabulators) go away with it.
        static::deleting(function (Event $event) {
            User::where('event_id', $event->id)->get()->each->delete();
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'ongoing';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** Tabulator accounts generated for this event. */
    public function tabulators()
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_TABULATOR)->orderBy('name');
    }

    /** Events a user may monitor: admins and general tabulators see all, event tabulators only theirs. */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isTabulator() && $user->event_id) {
            $query->whereKey($user->event_id);
        }

        return $query;
    }

    public function rounds()
    {
        return $this->hasMany(Round::class)->orderBy('sequence');
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    /** Logos picked for this event (logos marked "every event" are added in displayLogos()). */
    public function logos()
    {
        return $this->belongsToMany(Logo::class, 'event_logo');
    }

    /** Every logo to show for this event, in the library's order. */
    public function displayLogos()
    {
        return Logo::where('show_on_all', true)
            ->orWhereIn('id', $this->logos()->select('logos.id'))
            ->ordered()->get();
    }

    public function scores()
    {
        return $this->hasManyThrough(Score::class, Category::class);
    }

    public function contestants()
    {
        return $this->hasMany(Contestant::class)->orderBy('division')->orderBy('number');
    }

    public function judges()
    {
        return $this->belongsToMany(User::class, 'event_judge', 'event_id', 'user_id')->orderBy('name');
    }

    public function divisions(): array
    {
        return $this->contestants()->pluck('division')->map(fn ($d) => $d ?: null)->unique()->sort()->values()->all();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function statusBadge(): string
    {
        return ['draft' => 'badge-secondary', 'ongoing' => 'badge-primary', 'completed' => 'badge-success'][$this->status] ?? 'badge-secondary';
    }
}
