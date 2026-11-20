<?php

namespace Database\Seeders;

use App\Models\CategorySubmission;
use App\Models\Event;
use App\Models\Score;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create(['name' => 'System Administrator', 'username' => 'admin', 'email' => 'admin@example.com', 'password' => 'password', 'role' => 'admin']);

        User::create(['name' => 'Event Tabulator', 'username' => 'tabulator', 'password' => 'password', 'role' => 'tabulator']);

        $judges = collect(['Judge Maria Santos', 'Judge Jose Reyes', 'Judge Ana Cruz', 'Judge Paolo Garcia'])
            ->map(fn ($name, $i) => User::create([
                'name' => $name, 'username' => 'judge'.($i + 1), 'password' => 'password', 'role' => 'judge',
            ]));

        // ---------- Event 1: Pageant with Mr/Ms divisions, weighted average ----------
        $pageant = Event::create([
            'name' => 'Mr. & Ms. Foundation Day 2026', 'type' => 'pageant', 'venue' => 'University Gymnasium',
            'event_date' => now()->toDateString(), 'scoring_method' => 'average', 'status' => 'ongoing',
            'description' => 'Annual search for Mr. and Ms. Foundation Day.',
        ]);
        $pageant->judges()->sync($judges->take(3)->pluck('id'));

        $prelim = $pageant->rounds()->create(['name' => 'Preliminary Round', 'sequence' => 1]);
        $final = $pageant->rounds()->create(['name' => 'Final Round (Top 3)', 'sequence' => 2]);

        $setup = [
            ['Production Number', 15, [['Mastery & Gracefulness', 50], ['Stage Presence', 30], ['Overall Impact', 20]]],
            ['Sports Wear', 20, [['Physical Fitness', 40], ['Poise & Bearing', 40], ['Confidence', 20]]],
            ['Talent', 25, [['Mastery', 40], ['Creativity', 30], ['Audience Impact', 30]]],
            ['Formal Wear', 40, [['Elegance', 40], ['Poise & Bearing', 40], ['Overall Appeal', 20]]],
        ];
        foreach ($setup as $i => [$name, $weight, $criteria]) {
            $cat = $prelim->categories()->create(['event_id' => $pageant->id, 'name' => $name, 'weight' => $weight, 'sequence' => $i + 1]);
            foreach ($criteria as $j => [$cname, $cweight]) {
                $cat->criteria()->create(['name' => $cname, 'weight' => $cweight, 'min_score' => 0, 'max_score' => $cweight, 'sequence' => $j + 1]);
            }
        }
        $qa = $final->categories()->create(['event_id' => $pageant->id, 'name' => 'Question & Answer', 'weight' => 100, 'sequence' => 1]);
        $qa->criteria()->create(['name' => 'Wit & Content', 'weight' => 60, 'min_score' => 0, 'max_score' => 60, 'sequence' => 1]);
        $qa->criteria()->create(['name' => 'Delivery & Confidence', 'weight' => 40, 'min_score' => 0, 'max_score' => 40, 'sequence' => 2]);

        $towns = ['College of Engineering', 'College of Education', 'College of Nursing', 'College of Business', 'College of Arts & Sciences'];
        $mr = ['Carlo Dizon', 'Miguel Tan', 'Rafael Lim', 'Joshua Ramos', 'Enzo Villanueva'];
        $ms = ['Bea Navarro', 'Kristine Uy', 'Patricia Flores', 'Angelica Torres', 'Sofia Mendoza'];
        foreach (['Mr' => $mr, 'Ms' => $ms] as $division => $names) {
            foreach ($names as $i => $name) {
                $c = $pageant->contestants()->create(['number' => $i + 1, 'name' => $name, 'division' => $division, 'representing' => $towns[$i]]);
                $prelim->contestants()->attach($c->id);
            }
        }

        // Sample scores: the first two categories are fully scored and submitted, Talent is open.
        $prelim->load('categories.criteria', 'contestants');
        foreach ($prelim->categories as $index => $category) {
            if ($index < 2) {
                $this->fakeScores($category, $prelim->contestants, $pageant->judges, true);
                $category->update(['status' => 'locked']);
            } elseif ($index === 2) {
                $category->update(['status' => 'open']);
            }
        }

        // ---------- Event 2: Singing contest, rank-sum, running simultaneously ----------
        $singing = Event::create([
            'name' => 'Battle of the Voices 2026', 'type' => 'singing', 'venue' => 'Cultural Center Auditorium',
            'event_date' => now()->toDateString(), 'scoring_method' => 'ranking', 'status' => 'ongoing',
        ]);
        $singing->judges()->sync($judges->pluck('id'));
        $round = $singing->rounds()->create(['name' => 'Grand Finals', 'sequence' => 1]);
        $perf = $round->categories()->create(['event_id' => $singing->id, 'name' => 'Performance', 'weight' => 100, 'sequence' => 1, 'status' => 'open']);
        foreach ([['Voice Quality', 40], ['Interpretation', 30], ['Stage Presence', 20], ['Audience Impact', 10]] as $j => [$cname, $w]) {
            $perf->criteria()->create(['name' => $cname, 'weight' => $w, 'min_score' => 1, 'max_score' => 10, 'sequence' => $j + 1,
                'description' => 'Rate 1 (poor) to 10 (excellent)']);
        }
        foreach (['Jessa Morales', 'Mark Anthony Lopez', 'The Harmonics Trio', 'Rica Bautista', 'Daniel Ocampo', 'Lyka Fernandez'] as $i => $name) {
            $c = $singing->contestants()->create(['number' => $i + 1, 'name' => $name, 'representing' => 'Song #'.($i + 1)]);
            $round->contestants()->attach($c->id);
        }

        // ---------- Event 3: Draft event to show setup flow ----------
        $dance = Event::create([
            'name' => 'Inter-School Dance Showdown', 'type' => 'dance', 'venue' => 'City Sports Complex',
            'event_date' => now()->addWeeks(2)->toDateString(), 'scoring_method' => 'average', 'status' => 'draft',
        ]);
        $dance->rounds()->create(['name' => 'Main Round', 'sequence' => 1]);
    }

    protected function fakeScores($category, $contestants, $judges, bool $submit): void
    {
        foreach ($judges as $judge) {
            foreach ($contestants as $contestant) {
                // Give each contestant a stable "talent level" so results look realistic.
                $level = 0.72 + (crc32($contestant->name) % 20) / 100;
                foreach ($category->criteria as $criterion) {
                    $value = min($criterion->max_score, round($criterion->max_score * ($level + mt_rand(-5, 5) / 100), 2));
                    Score::create([
                        'category_id' => $category->id, 'criterion_id' => $criterion->id,
                        'contestant_id' => $contestant->id, 'judge_id' => $judge->id, 'score' => $value,
                    ]);
                }
            }
            if ($submit) {
                CategorySubmission::create(['category_id' => $category->id, 'judge_id' => $judge->id, 'submitted_at' => now()]);
            }
        }
    }
}
