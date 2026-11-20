<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\Score;
use App\Models\User;
use App\Services\TabulationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TabulationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    public function test_login_redirects_by_role(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'password'])->assertRedirect(route('home'));
        $this->get('/')->assertRedirect(route('admin.dashboard'));
        $this->post('/logout');

        $this->post('/login', ['username' => 'judge1', 'password' => 'password']);
        $this->get('/')->assertRedirect(route('judge.dashboard'));
    }

    public function test_admin_can_add_an_event(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.events.store'), [
            'name' => 'Search for Little Miss 2026',
            'type' => 'pageant',
            'venue' => 'Town Plaza',
            'event_date' => '2026-12-15',
            'scoring_method' => 'average',
            'status' => 'draft',
            'first_round' => 'Preliminaries',
        ]);

        $event = Event::where('name', 'Search for Little Miss 2026')->firstOrFail();
        $response->assertRedirect(route('admin.events.setup', $event));
        $this->assertSame('Preliminaries', $event->rounds()->first()->name);
    }

    public function test_every_admin_page_renders(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Active events');
        $this->get(route('admin.events.index'))->assertOk();
        $this->get(route('admin.events.create'))->assertOk()->assertSee('Create Event');
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.users.create'))->assertOk();

        foreach (Event::with('rounds')->get() as $event) {
            $this->get(route('admin.events.show', $event))->assertOk();
            $this->get(route('admin.events.edit', $event))->assertOk();
            $this->get(route('admin.events.setup', $event))->assertOk();
            $this->get(route('admin.contestants.index', $event))->assertOk();
            $this->get(route('admin.events.judges', $event))->assertOk();
            $this->get(route('admin.events.monitor', $event))->assertOk();
            $this->get(route('admin.events.results', $event))->assertOk();
            foreach ($event->rounds as $round) {
                $this->get(route('admin.events.results', [$event, 'round' => $round->id]))->assertOk();
                $this->get(route('admin.rounds.participants', $round))->assertOk();
                $this->get(route('admin.rounds.print', [$round, 'detailed' => 1]))->assertOk();
            }
        }
    }

    public function test_judge_scores_and_submits_a_category(): void
    {
        $judge = User::where('username', 'judge1')->first();
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $talent->load('criteria', 'round.contestants');
        $this->actingAs($judge);

        $this->get(route('judge.dashboard'))->assertOk()->assertSee('Mr. &amp; Ms. Foundation Day 2026', false);
        $this->get(route('judge.event', $talent->event_id))->assertOk();
        $this->get(route('judge.sheet', $talent))->assertOk()->assertSee('Submit Final Scores');

        $first = $talent->criteria->first();
        $contestant = $talent->round->contestants->first();

        // Out-of-range autosave is rejected.
        $this->postJson(route('judge.scores.autosave', $talent), [
            'contestant_id' => $contestant->id, 'criterion_id' => $first->id, 'score' => $first->max_score + 1,
        ])->assertStatus(422);

        $this->postJson(route('judge.scores.autosave', $talent), [
            'contestant_id' => $contestant->id, 'criterion_id' => $first->id, 'score' => $first->max_score,
        ])->assertOk()->assertJson(['saved' => true]);

        // Submitting an incomplete sheet fails.
        $this->post(route('judge.scores.save', $talent), ['action' => 'submit'])->assertSessionHas('error');

        $scores = [];
        foreach ($talent->round->contestants as $c) {
            foreach ($talent->criteria as $cr) {
                $scores[$c->id][$cr->id] = $cr->max_score * 0.9;
            }
        }
        $this->post(route('judge.scores.save', $talent), ['action' => 'submit', 'scores' => $scores])
            ->assertRedirect(route('judge.event', $talent->event_id));
        $this->assertDatabaseHas('category_submissions', ['category_id' => $talent->id, 'judge_id' => $judge->id]);

        // Locked after submission.
        $this->postJson(route('judge.scores.autosave', $talent), [
            'contestant_id' => $contestant->id, 'criterion_id' => $first->id, 'score' => 1,
        ])->assertStatus(423);

        // Admin unlocks it.
        $this->actingAs($this->admin())->delete(route('admin.categories.unlock', [$talent, $judge]));
        $this->assertDatabaseMissing('category_submissions', ['category_id' => $talent->id, 'judge_id' => $judge->id]);
    }

    public function test_judge_cannot_access_admin_or_unassigned_events(): void
    {
        $judge4 = User::where('username', 'judge4')->first(); // not a pageant judge
        $pageant = Event::where('type', 'pageant')->first();
        $this->actingAs($judge4);

        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('judge.event', $pageant))->assertForbidden();
    }

    public function test_weighted_average_and_rank_sum_computation(): void
    {
        $event = Event::create(['name' => 'Math Check', 'type' => 'other', 'scoring_method' => 'average', 'status' => 'ongoing']);
        [$j1, $j2] = [User::where('username', 'judge1')->first(), User::where('username', 'judge2')->first()];
        $event->judges()->sync([$j1->id, $j2->id]);
        $round = $event->rounds()->create(['name' => 'R1', 'sequence' => 1]);

        $catA = $round->categories()->create(['event_id' => $event->id, 'name' => 'A', 'weight' => 60]);
        $a1 = $catA->criteria()->create(['name' => 'a1', 'weight' => 100, 'min_score' => 0, 'max_score' => 10]);
        $catB = $round->categories()->create(['event_id' => $event->id, 'name' => 'B', 'weight' => 40]);
        $b1 = $catB->criteria()->create(['name' => 'b1', 'weight' => 50, 'min_score' => 0, 'max_score' => 50]);
        $b2 = $catB->criteria()->create(['name' => 'b2', 'weight' => 50, 'min_score' => 0, 'max_score' => 50]);

        $x = $event->contestants()->create(['number' => 1, 'name' => 'X']);
        $y = $event->contestants()->create(['number' => 2, 'name' => 'Y']);
        $z = $event->contestants()->create(['number' => 3, 'name' => 'Z']);
        $round->contestants()->sync([$x->id, $y->id, $z->id]);

        $put = fn ($cat, $crit, $c, $j, $s) => Score::create(['category_id' => $cat->id, 'criterion_id' => $crit->id, 'contestant_id' => $c->id, 'judge_id' => $j->id, 'score' => $s]);
        // Category A (0..10 → ×10):  X: 9, 7   Y: 8, 8   Z: 6, 10
        foreach ([[$x, 9, 7], [$y, 8, 8], [$z, 6, 10]] as [$c, $s1, $s2]) {
            $put($catA, $a1, $c, $j1, $s1);
            $put($catA, $a1, $c, $j2, $s2);
        }
        // Category B (two criteria of 50 each, direct points)
        foreach ([[$x, 40, 40, 45, 45], [$y, 45, 45, 40, 40], [$z, 30, 30, 50, 50]] as [$c, $p1, $p2, $q1, $q2]) {
            $put($catB, $b1, $c, $j1, $p1);
            $put($catB, $b2, $c, $j1, $p2);
            $put($catB, $b1, $c, $j2, $q1);
            $put($catB, $b2, $c, $j2, $q2);
        }

        $service = app(TabulationService::class);
        $overall = $service->roundResults($round->fresh())['divisions']['']['overall'];

        // X: A avg 80 → 48, B avg 85 → 34 ⇒ 82.   Y: A 80 → 48, B 85 → 34 ⇒ 82.   Z: A 80 → 48, B 80 → 32 ⇒ 80.
        $this->assertEqualsWithDelta(82, $overall[$x->id]['average'], 0.001);
        $this->assertEqualsWithDelta(82, $overall[$y->id]['average'], 0.001);
        $this->assertEqualsWithDelta(80, $overall[$z->id]['average'], 0.001);
        $this->assertSame(1, $overall[$x->id]['rank']);
        $this->assertSame(1, $overall[$y->id]['rank']); // tie shares the placement
        $this->assertSame(3, $overall[$z->id]['rank']);

        // Rank-sum: J1 totals X 86, Y 84, Z 60 → ranks 1,2,3;  J2 totals X 78, Y 80, Z 100 → ranks 3,2,1.
        $event->update(['scoring_method' => 'ranking']);
        $overall = $service->roundResults($round->fresh())['divisions']['']['overall'];
        $this->assertEquals(4, $overall[$x->id]['rank_sum']);
        $this->assertEquals(4, $overall[$y->id]['rank_sum']);
        $this->assertEquals(4, $overall[$z->id]['rank_sum']);
        // All tied on rank sum → broken by average: X and Y (82) share 1st, Z 3rd.
        $this->assertSame(1, $overall[$x->id]['rank']);
        $this->assertSame(1, $overall[$y->id]['rank']);
        $this->assertSame(3, $overall[$z->id]['rank']);
    }

    public function test_advance_top_contestants_to_next_round(): void
    {
        $pageant = Event::where('type', 'pageant')->first();
        $final = $pageant->rounds()->where('sequence', 2)->first();

        $this->actingAs($this->admin())->post(route('admin.rounds.advance', $final), ['top' => 3])
            ->assertRedirect(route('admin.events.setup', $pageant));

        // 3 per division (Mr & Ms), ties at the cut-off may add more.
        $this->assertGreaterThanOrEqual(6, $final->contestants()->count());
        $this->assertEqualsCanonicalizing(['Mr', 'Ms'], $final->contestants()->pluck('division')->unique()->values()->all());
    }
    public function test_tabulator_can_monitor_but_not_set_up_events(): void
    {
        $tabulator = User::where('username', 'tabulator')->firstOrFail();
        $this->post('/login', ['username' => 'tabulator', 'password' => 'password']);
        $this->get('/')->assertRedirect(route('tabulator.dashboard'));
        $this->get(route('tabulator.dashboard'))->assertOk()->assertSee('Live Events');

        $event = Event::where('status', 'ongoing')->with('rounds')->firstOrFail();
        $this->get(route('admin.events.monitor', $event))->assertOk()->assertSee('Scoring Monitor')->assertDontSee('Rounds &amp; Criteria', false);
        $this->get(route('admin.events.results', $event))->assertOk();
        $this->get(route('admin.events.pulse', $event))->assertOk()->assertJsonStructure(['version']);
        $this->get(route('admin.rounds.print', $event->rounds->first()))->assertOk();

        // Can open/lock categories and unlock a judge's sheet...
        $category = Category::where('event_id', $event->id)->where('status', 'locked')->firstOrFail();
        $this->patch(route('admin.categories.status', $category), ['status' => 'open'])->assertRedirect();
        $this->assertSame('open', $category->fresh()->status);
        $judge = $event->judges()->first();
        $this->delete(route('admin.categories.unlock', [$category, $judge]))->assertRedirect();
        $this->assertFalse($category->submissions()->where('judge_id', $judge->id)->exists());

        // ...but not manage events, users or scoring setup.
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.events.index'))->assertForbidden();
        $this->get(route('admin.events.setup', $event))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->delete(route('admin.events.destroy', $event))->assertForbidden();
        $this->get(route('judge.dashboard'))->assertForbidden();
        $this->assertTrue($tabulator->canTabulate());
    }

    public function test_admin_can_create_a_tabulator_account(): void
    {
        $this->actingAs($this->admin())->post(route('admin.users.store'), [
            'name' => 'Head Tabulator', 'username' => 'tab2', 'role' => 'tabulator',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'is_active' => 1,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame('tabulator', User::where('username', 'tab2')->value('role'));
    }

    public function test_every_keystroke_autosave_updates_live_results_immediately(): void
    {
        $judge = User::where('username', 'judge1')->first();
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $event = $talent->event;
        $criterion = $talent->criteria()->first();
        $contestant = $talent->round->contestants()->first();
        $tabulation = app(TabulationService::class);

        $before = $tabulation->version($event);

        // The judge types "1", then "12" into the same cell: each keystroke is saved, last value wins.
        foreach (['1', '12'] as $typed) {
            $this->actingAs($judge)->postJson(route('judge.scores.autosave', $talent), [
                'contestant_id' => $contestant->id, 'criterion_id' => $criterion->id, 'score' => $typed,
            ])->assertOk()->assertJson(['saved' => true]);
        }
        $this->assertSame(1, Score::where(['judge_id' => $judge->id, 'criterion_id' => $criterion->id, 'contestant_id' => $contestant->id])->count());
        $this->assertEquals(12, Score::where(['judge_id' => $judge->id, 'criterion_id' => $criterion->id, 'contestant_id' => $contestant->id])->value('score'));

        // Live screens see the change straight away (even within the same second).
        $afterTyping = $tabulation->version($event);
        $this->assertNotSame($before, $afterTyping);

        $this->postJson(route('judge.scores.autosave', $talent), [
            'contestant_id' => $contestant->id, 'criterion_id' => $criterion->id, 'score' => '13',
        ])->assertOk();
        $this->assertNotSame($afterTyping, $tabulation->version($event));

        // Clearing the cell (backspace) removes the score.
        $this->postJson(route('judge.scores.autosave', $talent), [
            'contestant_id' => $contestant->id, 'criterion_id' => $criterion->id, 'score' => null,
        ])->assertOk();
        $this->assertFalse(Score::where(['judge_id' => $judge->id, 'criterion_id' => $criterion->id, 'contestant_id' => $contestant->id])->exists());

        // Tabulator sees the pulse change.
        $this->actingAs(User::where('username', 'tabulator')->first())
            ->getJson(route('admin.events.pulse', $event))
            ->assertOk()->assertJson(['version' => $tabulation->version($event)]);
    }
    public function test_round_can_start_from_zero_or_carry_a_bearing_of_the_previous_round(): void
    {
        $event = Event::create(['name' => 'Carry Check', 'type' => 'pageant', 'scoring_method' => 'average', 'status' => 'ongoing']);
        $judge = User::where('username', 'judge1')->first();
        $event->judges()->sync([$judge->id]);
        $a = $event->contestants()->create(['number' => 1, 'name' => 'Alpha']);
        $b = $event->contestants()->create(['number' => 2, 'name' => 'Bravo']);

        $prelim = $event->rounds()->create(['name' => 'Prelim', 'sequence' => 1]);
        $final = $event->rounds()->create(['name' => 'Top 2', 'sequence' => 2]);
        $prelim->contestants()->sync([$a->id, $b->id]);
        $final->contestants()->sync([$a->id, $b->id]);

        $make = function ($round) use ($event) {
            $cat = $round->categories()->create(['event_id' => $event->id, 'name' => 'C'.$round->id, 'weight' => 100, 'sequence' => 1]);

            return [$cat, $cat->criteria()->create(['name' => 'Overall', 'weight' => 100, 'min_score' => 0, 'max_score' => 100, 'sequence' => 1])];
        };
        [$pc, $pk] = $make($prelim);
        [$fc, $fk] = $make($final);
        $put = fn ($cat, $crit, $c, $v) => Score::create(['category_id' => $cat->id, 'criterion_id' => $crit->id, 'contestant_id' => $c->id, 'judge_id' => $judge->id, 'score' => $v]);
        $put($pc, $pk, $a, 90); $put($pc, $pk, $b, 60);   // Alpha dominated the prelims
        $put($fc, $fk, $a, 70); $put($fc, $fk, $b, 80);   // Bravo wins the final round itself

        $service = app(TabulationService::class);
        $overall = fn () => $service->roundResults($final->fresh())['divisions']['']['overall'];

        // Default: scores go back to zero, Bravo wins.
        $this->assertSame(1, $overall()[$b->id]['rank']);
        $this->assertNull($service->roundResults($final->fresh())['carry']);

        // 50% bearing: Alpha 0.5*70 + 0.5*90 = 80 beats Bravo 0.5*80 + 0.5*60 = 70.
        $final->update(['carry_over' => 50]);
        $rows = $overall();
        $this->assertEqualsWithDelta(80, $rows[$a->id]['average'], 0.001);
        $this->assertEqualsWithDelta(70, $rows[$b->id]['average'], 0.001);
        $this->assertSame(1, $rows[$a->id]['rank']);
        $this->assertEqualsWithDelta(70, $rows[$a->id]['round_score'], 0.001);
        $this->assertEqualsWithDelta(90, $rows[$a->id]['carried'], 0.001);

        // Rank-sum events blend each judge's score too.
        $event->update(['scoring_method' => 'ranking']);
        $this->assertSame(1, $overall()[$a->id]['rank']);

        // Admin can set it from the round form; pages render.
        $this->actingAs($this->admin())->put(route('admin.rounds.update', $final), ['name' => 'Top 2', 'sequence' => 2, 'carry_over' => 30])->assertRedirect();
        $this->assertEquals(30, $final->fresh()->carry_over);
        $this->get(route('admin.events.results', [$event, 'round' => $final->id]))->assertOk()->assertSeeText('30% of Prelim');
        $this->get(route('admin.rounds.print', $final))->assertOk();
        $this->get(route('admin.events.setup', $event))->assertOk()->assertSee('30% bearing of previous round');
    }
    public function test_live_results_chart_views_render(): void
    {
        $this->actingAs($this->admin());
        foreach (Event::with('rounds')->where('status', 'ongoing')->get() as $event) {
            foreach ($event->rounds as $round) {
                foreach (['table', 'standings', 'breakdown', 'judges', 'categories', 'podium'] as $view) {
                    $this->get(route('admin.events.results', [$event, 'round' => $round->id, 'view' => $view]))->assertOk();
                    $this->get(route('admin.events.results', [$event, 'round' => $round->id, 'view' => $view]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
                }
            }
        }

        $prelim = Event::where('name', 'Mr. & Ms. Foundation Day 2026')->firstOrFail()->rounds()->first();
        $this->get(route('admin.events.results', [$prelim->event_id, 'round' => $prelim->id, 'view' => 'breakdown']))
            ->assertSee('viz-seg', false)->assertSee('Production Number');
        $this->get(route('admin.events.results', [$prelim->event_id, 'round' => $prelim->id, 'view' => 'judges']))
            ->assertSee('viz-heat', false);
        $this->get(route('admin.events.results', [$prelim->event_id, 'round' => $prelim->id, 'view' => 'podium']))
            ->assertSee('viz-podium-tile', false);
    }
}
