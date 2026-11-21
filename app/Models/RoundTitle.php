<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoundTitle extends Model
{
    protected $fillable = ['round_id', 'rank', 'division', 'title'];

    protected function casts(): array
    {
        return ['rank' => 'integer'];
    }

    public function round()
    {
        return $this->belongsTo(Round::class);
    }

    /** Ready-made sets the admin can apply with one click. */
    public static function presets(): array
    {
        return [
            'winner' => ['Winner', '1st Runner-Up', '2nd Runner-Up', '3rd Runner-Up', '4th Runner-Up'],
            'champion' => ['Champion', '1st Runner-Up', '2nd Runner-Up'],
            'places' => ['1st Place', '2nd Place', '3rd Place', '4th Place', '5th Place'],
        ];
    }
}
