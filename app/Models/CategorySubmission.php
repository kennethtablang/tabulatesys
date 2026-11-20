<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategorySubmission extends Model
{
    public $timestamps = false;

    protected $fillable = ['category_id', 'judge_id', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function judge()
    {
        return $this->belongsTo(User::class, 'judge_id');
    }
}
