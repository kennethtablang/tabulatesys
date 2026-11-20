<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'open' => 'Open for scoring', 'locked' => 'Locked'];

    protected $fillable = ['event_id', 'round_id', 'name', 'weight', 'sequence', 'status'];

    protected function casts(): array
    {
        return ['weight' => 'float'];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function round()
    {
        return $this->belongsTo(Round::class);
    }

    public function criteria()
    {
        return $this->hasMany(Criterion::class)->orderBy('sequence');
    }

    public function scores()
    {
        return $this->hasMany(Score::class);
    }

    public function submissions()
    {
        return $this->hasMany(CategorySubmission::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function criteriaWeight(): float
    {
        return (float) $this->criteria->sum('weight');
    }

    public function statusBadge(): string
    {
        return ['pending' => 'badge-secondary', 'open' => 'badge-primary', 'locked' => 'badge-danger'][$this->status] ?? 'badge-secondary';
    }
}
