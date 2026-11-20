<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Score extends Model
{
    protected $fillable = ['category_id', 'criterion_id', 'contestant_id', 'judge_id', 'score'];

    protected function casts(): array
    {
        return ['score' => 'float'];
    }

    public function criterion()
    {
        return $this->belongsTo(Criterion::class);
    }

    public function contestant()
    {
        return $this->belongsTo(Contestant::class);
    }

    public function judge()
    {
        return $this->belongsTo(User::class, 'judge_id');
    }
}
