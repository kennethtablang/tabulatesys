<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contestant extends Model
{
    protected $fillable = ['event_id', 'number', 'name', 'division', 'representing', 'details', 'photo'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function rounds()
    {
        return $this->belongsToMany(Round::class, 'contestant_round');
    }

    public function photoUrl(): string
    {
        return $this->photo ? asset('uploads/'.$this->photo) : asset('inspinia/img/profile_small.jpg');
    }

    public function label(): string
    {
        return '#'.$this->number.' '.$this->name;
    }
}
