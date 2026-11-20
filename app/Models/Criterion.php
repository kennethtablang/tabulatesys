<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Criterion extends Model
{
    protected $table = 'criteria';

    protected $fillable = ['category_id', 'name', 'description', 'weight', 'min_score', 'max_score', 'sequence'];

    protected function casts(): array
    {
        return ['weight' => 'float', 'min_score' => 'float', 'max_score' => 'float'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /** Convert a raw judge score into its weighted contribution to the category (0..weight). */
    public function contribution(float $score): float
    {
        return $this->max_score > 0 ? ($score / $this->max_score) * $this->weight : 0;
    }
}
