<?php

namespace App\Http\Controllers\Judge;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\CategorySubmission;
use App\Models\Score;
use App\Models\ScoreLog;
use App\Support\JudgeDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScoreController extends Controller
{
    public function sheet(Request $request, Category $category)
    {
        $this->authorizeJudge($category);
        $category->load(['criteria', 'round.contestants', 'event']);
        $judgeId = auth()->id();

        $scores = Score::where('category_id', $category->id)->where('judge_id', $judgeId)->get()
            ->groupBy('contestant_id')
            ->map(fn ($rows) => $rows->pluck('score', 'criterion_id'));

        $submission = CategorySubmission::where('category_id', $category->id)->where('judge_id', $judgeId)->first();
        $editable = $this->editable($category, $submission);
        $display = JudgeDisplay::forEvent($category->event, $request->query('layout'));
        $contestants = JudgeDisplay::sort($category->round->contestants, $display['contestant_order']);
        $preview = false;

        return view('judge.sheet', compact('category', 'scores', 'submission', 'editable', 'display', 'contestants', 'preview'));
    }

    /** Save the whole score sheet; with action=submit it is also finalized. */
    public function save(Request $request, Category $category)
    {
        $this->authorizeJudge($category);
        $category->load(['criteria', 'round.contestants', 'event']);
        $this->ensureEditable($category);

        $criteria = $category->criteria->keyBy('id');
        $contestantIds = $category->round->contestants->pluck('id')->all();
        $input = (array) $request->input('scores', []);
        $errors = [];

        DB::transaction(function () use ($input, $criteria, $contestantIds, $category, &$errors) {
            foreach ($input as $contestantId => $values) {
                if (! in_array((int) $contestantId, $contestantIds, true)) {
                    continue;
                }
                foreach ((array) $values as $criterionId => $value) {
                    $criterion = $criteria->get((int) $criterionId);
                    if (! $criterion) {
                        continue;
                    }
                    if ($value === null || $value === '') {
                        $this->deleteScore($category, $criterion->id, (int) $contestantId, 'sheet');

                        continue;
                    }
                    if (! is_numeric($value) || $value < $criterion->min_score || $value > $criterion->max_score) {
                        $errors["scores.$contestantId.$criterionId"] =
                            "{$criterion->name}: score must be between {$criterion->min_score} and {$criterion->max_score}.";

                        continue;
                    }
                    $this->putScore($category, $criterion->id, (int) $contestantId, (float) $value, 'sheet');
                }
            }
        });

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        if ($request->input('action') === 'submit') {
            return $this->finalize($category);
        }

        return back()->with('success', 'Scores saved. You can still edit them until you submit.');
    }

    /** Save a single cell (used by the score sheet's auto-save). */
    public function autosave(Request $request, Category $category)
    {
        $this->authorizeJudge($category);
        $category->load(['criteria', 'round.contestants', 'event']);
        $this->ensureEditable($category);

        $data = $request->validate([
            'contestant_id' => ['required', 'integer'],
            'criterion_id' => ['required', 'integer'],
            'score' => ['nullable', 'numeric'],
        ]);

        $criterion = $category->criteria->firstWhere('id', $data['criterion_id']);
        abort_unless($criterion && $category->round->contestants->contains('id', $data['contestant_id']), 422, 'Invalid score cell.');

        if ($data['score'] === null) {
            $this->deleteScore($category, $criterion->id, (int) $data['contestant_id'], 'autosave');
        } else {
            if ($data['score'] < $criterion->min_score || $data['score'] > $criterion->max_score) {
                return response()->json([
                    'message' => "Score must be between {$criterion->min_score} and {$criterion->max_score}.",
                ], 422);
            }
            $this->putScore($category, $criterion->id, (int) $data['contestant_id'], (float) $data['score'], 'autosave');
        }

        return response()->json(['saved' => true, 'at' => now()->format('h:i:s A')]);
    }

    public function submit(Category $category)
    {
        $this->authorizeJudge($category);
        $category->load(['criteria', 'round.contestants', 'event']);
        $this->ensureEditable($category);

        return $this->finalize($category);
    }

    protected function finalize(Category $category)
    {
        $expected = $category->criteria->count() * $category->round->contestants->count();
        $entered = Score::where('category_id', $category->id)
            ->where('judge_id', auth()->id())
            ->whereIn('contestant_id', $category->round->contestants->pluck('id'))
            ->whereIn('criterion_id', $category->criteria->pluck('id'))
            ->count();

        if ($entered < $expected) {
            return back()->with('error', 'Please score every contestant on every criterion before submitting ('.$entered.' of '.$expected.' entered).');
        }

        CategorySubmission::firstOrCreate(
            ['category_id' => $category->id, 'judge_id' => auth()->id()],
            ['submitted_at' => now()]
        );
        ActivityLog::record('submission', 'Submitted final scores for "'.$category->name.'".', $category->event);

        return redirect()->route('judge.event', $category->event_id)
            ->with('success', 'Your scores for "'.$category->name.'" have been submitted. Thank you!');
    }

    protected function putScore(Category $category, int $criterionId, int $contestantId, float $value, string $source): void
    {
        $value = round($value, 2);
        $existing = Score::where(['criterion_id' => $criterionId, 'contestant_id' => $contestantId, 'judge_id' => auth()->id()])->first();
        $old = $existing ? (float) $existing->score : null;

        if ($old === $value) {
            return;
        }
        if (! $existing && ! Score::where(['category_id' => $category->id, 'judge_id' => auth()->id()])->exists()) {
            ActivityLog::record('scoring', 'Started scoring "'.$category->name.'".', $category->event);
        }

        Score::updateOrCreate(
            ['criterion_id' => $criterionId, 'contestant_id' => $contestantId, 'judge_id' => auth()->id()],
            ['category_id' => $category->id, 'score' => $value]
        );
        ScoreLog::record($category, $criterionId, $contestantId, auth()->id(), $old, $value, $source);
    }

    protected function deleteScore(Category $category, int $criterionId, int $contestantId, string $source): void
    {
        $existing = Score::where(['category_id' => $category->id, 'criterion_id' => $criterionId, 'contestant_id' => $contestantId, 'judge_id' => auth()->id()])->first();

        if ($existing) {
            $existing->delete();
            ScoreLog::record($category, $criterionId, $contestantId, auth()->id(), (float) $existing->score, null, $source);
        }
    }

    protected function authorizeJudge(Category $category): void
    {
        abort_unless(
            $category->event->judges()->whereKey(auth()->id())->exists(),
            403,
            'You are not a judge of this event.'
        );
    }

    protected function editable(Category $category, ?CategorySubmission $submission): bool
    {
        return ! $submission && $category->isOpen() && $category->event->status === 'ongoing';
    }

    protected function ensureEditable(Category $category): void
    {
        if (\App\Support\ScreenLock::isLocked(auth()->user(), $category->event_id)) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(request()->expectsJson()
                ? response()->json(['message' => \App\Support\ScreenLock::message(), 'screen_locked' => true], 423)
                : back()->with('error', \App\Support\ScreenLock::message()));
        }

        $submission = CategorySubmission::where('category_id', $category->id)->where('judge_id', auth()->id())->first();

        if (! $this->editable($category, $submission)) {
            $message = $submission
                ? 'You already submitted this category. Ask the administrator to unlock it.'
                : 'This category is not open for scoring.';
            abort(request()->expectsJson() ? 423 : 403, $message);
        }
    }
}
