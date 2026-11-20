<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreLog extends Model
{
    protected $fillable = ['event_id', 'category_id', 'criterion_id', 'contestant_id', 'judge_id', 'old_score', 'new_score', 'source', 'ip_address'];

    protected function casts(): array
    {
        return ['old_score' => 'float', 'new_score' => 'float'];
    }

    public function event() { return $this->belongsTo(Event::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function criterion() { return $this->belongsTo(Criterion::class); }
    public function contestant() { return $this->belongsTo(Contestant::class); }
    public function judge() { return $this->belongsTo(User::class, 'judge_id'); }

    /**
     * Record a change to one score cell. Keystrokes typed within a few seconds on the same
     * cell are merged into one entry ("8" then "8.5" is logged once as → 8.5).
     */
    public static function record(Category $category, int $criterionId, int $contestantId, int $judgeId, ?float $old, ?float $new, string $source): void
    {
        if ($old === $new) {
            return;
        }

        $recent = static::where(['judge_id' => $judgeId, 'criterion_id' => $criterionId, 'contestant_id' => $contestantId, 'source' => $source])
            ->where('updated_at', '>=', now()->subSeconds(10))
            ->latest('id')
            ->first();

        if ($recent) {
            if ($recent->old_score === $new) {
                $recent->delete();           // typed and then undone: nothing really changed
            } else {
                $recent->update(['new_score' => $new]);
            }

            return;
        }

        static::create([
            'event_id' => $category->event_id, 'category_id' => $category->id,
            'criterion_id' => $criterionId, 'contestant_id' => $contestantId, 'judge_id' => $judgeId,
            'old_score' => $old, 'new_score' => $new, 'source' => $source, 'ip_address' => request()?->ip(),
        ]);
    }
}
