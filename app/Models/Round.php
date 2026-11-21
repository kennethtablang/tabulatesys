<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Round extends Model
{
    protected $fillable = ['event_id', 'name', 'sequence', 'carry_over'];

    protected function casts(): array
    {
        return ['carry_over' => 'float'];
    }

    /** % of the previous round's final score counted in this round (0 = start from zero). */
    public function carryOver(): float
    {
        return $this->previous() ? max(0.0, min(100.0, (float) $this->carry_over)) : 0.0;
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class)->orderBy('sequence');
    }

    public function titles()
    {
        return $this->hasMany(RoundTitle::class)->orderBy('rank');
    }

    /** Title for a placement, preferring the division's own title over the "all divisions" one. */
    public function titleFor(?int $rank, ?string $division): ?string
    {
        if (! $rank) {
            return null;
        }
        $titles = $this->relationLoaded('titles') ? $this->titles : $this->titles()->get();
        $own = $division ? $titles->first(fn ($t) => $t->rank === $rank && $t->division === $division) : null;

        return ($own ?? $titles->first(fn ($t) => $t->rank === $rank && $t->division === null))?->title;
    }

    /** How many places have a title (used as the default "Top N"). */
    public function titledPlaces(): int
    {
        return (int) $this->titles()->max('rank');
    }

    public function contestants()
    {
        return $this->belongsToMany(Contestant::class, 'contestant_round')->orderBy('division')->orderBy('number');
    }

    public function previous(): ?Round
    {
        return static::where('event_id', $this->event_id)
            ->where('sequence', '<', $this->sequence)
            ->orderByDesc('sequence')
            ->first();
    }

    public function totalWeight(): float
    {
        return (float) $this->categories->sum('weight');
    }
}
