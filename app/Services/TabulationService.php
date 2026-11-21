<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategorySubmission;
use App\Models\Event;
use App\Models\Round;
use App\Models\Score;
use Illuminate\Support\Collection;

/**
 * Computes results for rounds/categories.
 *
 * Score model:
 *  - A judge enters a raw score per criterion (min..max).
 *  - Criterion contribution = score / max * criterion weight  → a category score on a 0–100 scale.
 *  - Round score = Σ category score × category weight / 100.
 *
 * Methods:
 *  - average: contestants are ranked by the judges' average (highest wins).
 *  - ranking: every judge's scores are converted to ranks (ties share the averaged position);
 *             ranks are summed across judges and the lowest rank-sum wins (average breaks ties).
 */
class TabulationService
{
    /** Judge → contestant → category score (0..100) built from raw scores. */
    public function categoryMatrix(Category $category, Collection $scores): array
    {
        $criteria = $category->criteria->keyBy('id');
        $matrix = [];

        foreach ($scores as $score) {
            $criterion = $criteria->get($score->criterion_id);
            if (! $criterion) {
                continue;
            }
            $matrix[$score->judge_id][$score->contestant_id] =
                ($matrix[$score->judge_id][$score->contestant_id] ?? 0) + $criterion->contribution($score->score);
        }

        return $matrix;
    }

    /**
     * Full results of a round, grouped by division.
     *
     * @return array{judges: Collection, categories: Collection, divisions: array<string, array{overall: array, categories: array}>}
     */
    public function roundResults(Round $round): array
    {
        $round->loadMissing(['event.judges', 'categories.criteria', 'contestants', 'titles']);
        $event = $round->event;
        $judgeIds = $event->judges->pluck('id')->all();
        $categories = $round->categories;

        // Optional bearing of the previous round: its final score (0-100) counts for `carry_over` %.
        $carry = null;
        $percent = $round->carryOver();
        if ($percent > 0 && ($previousRound = $round->previous())) {
            $carryScores = [];
            foreach ($this->roundResults($previousRound)['divisions'] as $prevDivision) {
                foreach ($prevDivision['overall'] as $cid => $prevRow) {
                    $carryScores[$cid] = $prevRow['average'];
                }
            }
            $carry = ['percent' => $percent, 'from' => $previousRound, 'scores' => $carryScores];
        }

        $scoresByCategory = Score::whereIn('category_id', $categories->pluck('id'))
            ->get()
            ->groupBy('category_id');

        $matrices = [];
        foreach ($categories as $category) {
            $matrices[$category->id] = $this->categoryMatrix($category, $scoresByCategory->get($category->id, collect()));
        }

        $divisions = [];
        foreach ($round->contestants->groupBy(fn ($c) => $c->division ?? '') as $division => $contestants) {
            $contestants = $contestants->keyBy('id');

            // Per-category results.
            $categoryResults = [];
            foreach ($categories as $category) {
                $rows = $this->buildRows($contestants, $matrices[$category->id], $judgeIds);
                $categoryResults[$category->id] = $this->assignRanks($rows, $event->scoring_method);
            }

            // Round matrix: judge → contestant → weighted round total.
            $roundMatrix = [];
            foreach ($categories as $category) {
                foreach ($matrices[$category->id] as $judgeId => $byContestant) {
                    foreach ($byContestant as $contestantId => $value) {
                        if ($contestants->has($contestantId)) {
                            $roundMatrix[$judgeId][$contestantId] =
                                ($roundMatrix[$judgeId][$contestantId] ?? 0) + $value * $category->weight / 100;
                        }
                    }
                }
            }

            // This round's own score per contestant (before any carry-over), averaged over judges.
            $ownScores = [];
            foreach ($contestants as $cid => $_) {
                $values = array_filter(array_map(fn ($byContestant) => $byContestant[$cid] ?? null, $roundMatrix), fn ($v) => $v !== null);
                $ownScores[$cid] = $values ? array_sum($values) / count($values) : null;
            }

            // Blend every judge's score with the carried score, so judge ranks (rank-sum) include it too.
            if ($carry) {
                foreach ($roundMatrix as $judgeId => $byContestant) {
                    foreach ($byContestant as $cid => $value) {
                        $roundMatrix[$judgeId][$cid] = $this->blend($value, $carry['scores'][$cid] ?? null, $carry['percent']);
                    }
                }
            }

            $rows = $this->buildRows($contestants, $roundMatrix, $judgeIds);

            foreach ($rows as $contestantId => &$row) {
                $total = null;
                $row['categories'] = [];
                foreach ($categories as $category) {
                    $catRow = $categoryResults[$category->id][$contestantId] ?? null;
                    $avg = $catRow['average'] ?? null;
                    $row['categories'][$category->id] = [
                        'average' => $avg,
                        'weighted' => $avg === null ? null : $avg * $category->weight / 100,
                        'rank' => $catRow['rank'] ?? null,
                    ];
                    if ($avg !== null) {
                        $total = ($total ?? 0) + $avg * $category->weight / 100;
                    }
                }
                $carried = $carry ? ($carry['scores'][$contestantId] ?? null) : null;
                $row['round_score'] = $event->scoring_method === 'average' ? $total : $ownScores[$contestantId];
                $row['carried'] = $carried;
                $row['carried_points'] = $carry && $carried !== null ? $carried * $carry['percent'] / 100 : null;

                // For the average method the round total is the weighted sum of category averages
                // (blended with the previous round when it has a bearing).
                if ($event->scoring_method === 'average') {
                    $row['average'] = $carry ? $this->blend($total, $carried, $carry['percent']) : $total;
                }
            }
            unset($row);

            $divisions[$division] = [
                'overall' => array_map(function (array $row) use ($round, $division) {
                    $row['title'] = $round->titleFor($row['rank'], $division !== '' ? $division : null);

                    return $row;
                }, $this->sortByRank($this->assignRanks($rows, $event->scoring_method))),
                'categories' => array_map(fn ($r) => $this->sortByRank($r), $categoryResults),
            ];
        }

        ksort($divisions);

        return [
            'judges' => $event->judges, 'categories' => $categories, 'divisions' => $divisions,
            'carry' => $carry ? ['percent' => $carry['percent'], 'from' => $carry['from']] : null,
        ];
    }

    /** final = (100 - p)% of this round + p% of the previous round. No score yet in this round → no total. */
    protected function blend(?float $current, ?float $previous, float $percent): ?float
    {
        if ($current === null) {
            return null;
        }

        return $current * (100 - $percent) / 100 + ($previous ?? 0) * $percent / 100;
    }

    /** Build per-contestant rows with judge scores, judge ranks, average and rank-sum. */
    protected function buildRows(Collection $contestants, array $matrix, array $judgeIds): array
    {
        $rows = [];
        foreach ($contestants as $id => $contestant) {
            $rows[$id] = [
                'contestant' => $contestant,
                'judge_scores' => [],
                'judge_ranks' => [],
                'average' => null,
                'rank_sum' => null,
                'rank' => null,
            ];
        }

        $count = count($rows);
        foreach ($judgeIds as $judgeId) {
            $scores = [];
            foreach ($rows as $id => $_) {
                $value = $matrix[$judgeId][$id] ?? null;
                $rows[$id]['judge_scores'][$judgeId] = $value;
                if ($value !== null) {
                    $scores[$id] = round($value, 4);
                }
            }
            $ranks = $this->fractionalRanks($scores);
            foreach ($rows as $id => $_) {
                // A judge who has not scored a contestant gives them the last place in rank-sum mode.
                $rows[$id]['judge_ranks'][$judgeId] = empty($scores) ? null : ($ranks[$id] ?? $count);
            }
        }

        foreach ($rows as $id => &$row) {
            $values = array_filter($row['judge_scores'], fn ($v) => $v !== null);
            $row['average'] = $values ? array_sum($values) / count($values) : null;
            $ranks = array_filter($row['judge_ranks'], fn ($v) => $v !== null);
            $row['rank_sum'] = $ranks ? array_sum($ranks) : null;
        }
        unset($row);

        return $rows;
    }

    /** Highest score → rank 1; tied scores share the average of their positions (e.g. 2.5). */
    protected function fractionalRanks(array $scores): array
    {
        arsort($scores);
        $ranks = [];
        $values = array_values($scores);
        $keys = array_keys($scores);
        $i = 0;
        $n = count($values);
        while ($i < $n) {
            $j = $i;
            while ($j + 1 < $n && $values[$j + 1] == $values[$i]) {
                $j++;
            }
            $avgRank = (($i + 1) + ($j + 1)) / 2;
            for ($k = $i; $k <= $j; $k++) {
                $ranks[$keys[$k]] = $avgRank;
            }
            $i = $j + 1;
        }

        return $ranks;
    }

    /** Assign final placements (ties share the same placement: 1, 2, 2, 4). */
    protected function assignRanks(array $rows, string $method): array
    {
        $key = function (array $row) use ($method) {
            $avg = $row['average'] === null ? null : round($row['average'], 4);
            if ($method === 'ranking') {
                return [$row['rank_sum'] === null ? INF : $row['rank_sum'], $avg === null ? INF : -$avg];
            }

            return [$avg === null ? INF : -$avg];
        };

        $ids = array_keys($rows);
        usort($ids, fn ($a, $b) => $key($rows[$a]) <=> $key($rows[$b])
            ?: $rows[$a]['contestant']->number <=> $rows[$b]['contestant']->number);

        $position = 0;
        $previous = null;
        $previousRank = null;
        foreach ($ids as $index => $id) {
            $position = $index + 1;
            $current = $key($rows[$id]);
            $hasScore = $rows[$id]['average'] !== null;
            $rank = ($previous !== null && $current == $previous) ? $previousRank : $position;
            $rows[$id]['rank'] = $hasScore ? $rank : null;
            $previous = $current;
            $previousRank = $rank;
        }

        return $rows;
    }

    protected function sortByRank(array $rows): array
    {
        uasort($rows, fn ($a, $b) => [$a['rank'] ?? PHP_INT_MAX, $a['contestant']->number]
            <=> [$b['rank'] ?? PHP_INT_MAX, $b['contestant']->number]);

        return $rows;
    }

    /**
     * Scoring progress for every category of an event, per judge.
     *
     * @return array<int, array<int, array{entered:int, expected:int, submitted:bool, submitted_at:?string}>>
     */
    /**
     * A cheap fingerprint of everything that affects an event's live screens.
     * It changes whenever a judge saves, clears or submits a score, or a category/event changes,
     * so live pages can poll it and only re-render when something actually happened.
     */
    public function version(Event $event): string
    {
        $categoryIds = Category::where('event_id', $event->id)->pluck('id');

        $scores = Score::whereIn('category_id', $categoryIds)
            ->selectRaw('count(*) as c, coalesce(sum(score), 0) as s, max(id) as i, max(updated_at) as u')
            ->first();
        $submissions = CategorySubmission::whereIn('category_id', $categoryIds)
            ->selectRaw('count(*) as c, max(id) as i, max(submitted_at) as u')
            ->first();
        $categories = Category::where('event_id', $event->id)
            ->selectRaw('count(*) as c, max(updated_at) as u')
            ->first();

        return md5(json_encode([
            $event->fresh()?->updated_at, $event->judges()->count(),
            $scores?->toArray(), $submissions?->toArray(), $categories?->toArray(),
        ]));
    }

    public function progress(Event $event): array
    {
        $event->loadMissing(['judges', 'rounds.categories.criteria', 'rounds.contestants']);

        $categoryIds = $event->rounds->flatMap->categories->pluck('id');
        $entered = Score::whereIn('category_id', $categoryIds)
            ->selectRaw('category_id, judge_id, count(*) as total')
            ->groupBy('category_id', 'judge_id')
            ->get()
            ->groupBy('category_id');
        $submissions = CategorySubmission::whereIn('category_id', $categoryIds)->get()->groupBy('category_id');

        $progress = [];
        foreach ($event->rounds as $round) {
            foreach ($round->categories as $category) {
                $expected = $category->criteria->count() * $round->contestants->count();
                foreach ($event->judges as $judge) {
                    $sub = optional($submissions->get($category->id))->firstWhere('judge_id', $judge->id);
                    $progress[$category->id][$judge->id] = [
                        'entered' => (int) optional(optional($entered->get($category->id))->firstWhere('judge_id', $judge->id))->total,
                        'expected' => $expected,
                        'submitted' => (bool) $sub,
                        'submitted_at' => $sub?->submitted_at?->format('M d, h:i A'),
                    ];
                }
            }
        }

        return $progress;
    }

    /** Contestants ranked within the top N (per division) of a round, ties at the cut-off included. */
    public function topContestantIds(Round $round, int $top): array
    {
        $results = $this->roundResults($round);
        $ids = [];
        foreach ($results['divisions'] as $division) {
            foreach ($division['overall'] as $id => $row) {
                if ($row['rank'] !== null && $row['rank'] <= $top) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }
}
