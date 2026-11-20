<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Event;
use App\Models\ScoreLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin workflow: create event → generate judges & tabulator → activate → score → history. */
class EventWorkflowTest extends TestCase
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

    protected function login(string $username, string $password): void
    {
        $this->post('/logout');
        $this->post('/login', ['username' => $username, 'password' => $password])->assertRedirect(route('home'));
    }

    public function test_admin_generates_judge_accounts_for_an_event(): void
    {
        $event = Event::where('name', 'Battle of the Voices 2026')->firstOrFail();

        $response = $this->actingAs($this->admin())->post(route('admin.events.judges.generate', $event), [
            'names' => "Judge Alpha\n\n  Judge Beta  \nJudge Alpha",
        ]);
        $response->assertRedirect()->assertSessionHas('credentials');

        $slips = session('credentials');
        $this->assertCount(2, $slips);
        $this->assertSame(["ev{$event->id}-judge1", "ev{$event->id}-judge2"], array_column($slips, 'username'));
        $this->assertTrue($event->judges()->where('username', "ev{$event->id}-judge1")->exists());

        // The slip page shows the password once; the generated login works.
        $this->get(route('admin.events.judges', $event))->assertSee($slips[0]['password']);
        $this->login($slips[1]['username'], $slips[1]['password']);
        $this->get('/')->assertRedirect(route('judge.dashboard'));
        $this->get(route('judge.dashboard'))->assertSee('Battle of the Voices 2026');
    }

    public function test_generated_tabulator_only_sees_its_own_event(): void
    {
        $own = Event::where('name', 'Battle of the Voices 2026')->firstOrFail();
        $other = Event::where('name', 'Mr. & Ms. Foundation Day 2026')->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.events.tabulators.store', $own), ['name' => 'Head Tabulator'])
            ->assertSessionHas('credentials');
        $slip = session('credentials')[0];
        $this->assertSame("ev{$own->id}-tab1", $slip['username']);

        $this->login($slip['username'], $slip['password']);
        $this->get(route('tabulator.dashboard'))->assertOk()->assertSee($own->name)->assertDontSee($other->name);
        $this->get(route('admin.events.monitor', $own))->assertOk();
        $this->get(route('admin.events.results', $own))->assertOk();

        $this->get(route('admin.events.monitor', $other))->assertForbidden();
        $this->get(route('admin.events.results', $other))->assertForbidden();
        $this->get(route('admin.events.pulse', $other))->assertForbidden();
        $this->get(route('admin.rounds.print', $other->rounds()->first()))->assertForbidden();
        $otherCategory = Category::where('event_id', $other->id)->first();
        $this->patch(route('admin.categories.status', $otherCategory), ['status' => 'locked'])->assertForbidden();

        // Deleting the event removes its tabulator account.
        $this->actingAs($this->admin())->delete(route('admin.events.destroy', $own));
        $this->assertFalse(User::where('username', $slip['username'])->exists());
    }

    public function test_reset_password_issues_a_new_working_password(): void
    {
        $judge = User::where('username', 'judge2')->first();
        $this->actingAs($this->admin())->post(route('admin.users.reset-password', $judge))->assertSessionHas('credentials');
        $password = session('credentials')[0]['password'];

        $this->assertNotSame('password', $password);
        $this->login('judge2', $password);
        $this->post('/logout');
        $this->post('/login', ['username' => 'judge2', 'password' => 'password'])->assertSessionHasErrors('username');

        // Admin passwords can't be reset this way.
        $this->actingAs($this->admin())->post(route('admin.users.reset-password', $this->admin()))->assertForbidden();
    }

    public function test_tabulation_can_be_set_active_and_inactive(): void
    {
        $event = Event::where('name', 'Battle of the Voices 2026')->firstOrFail();
        $category = Category::where('event_id', $event->id)->firstOrFail();
        $judge = User::where('username', 'judge1')->first();
        $cell = ['contestant_id' => $category->round->contestants()->first()->id, 'criterion_id' => $category->criteria()->first()->id, 'score' => 7];

        $this->actingAs($this->admin())->patch(route('admin.events.status', $event), ['status' => 'draft'])->assertRedirect();
        $this->assertSame('Inactive', $event->fresh()->statusLabel());

        // Inactive: hidden from judges and no scoring.
        $this->actingAs($judge)->get(route('judge.dashboard'))->assertDontSee($event->name);
        $this->postJson(route('judge.scores.autosave', $category), $cell)->assertStatus(423);

        $this->actingAs($this->admin())->patch(route('admin.events.status', $event), ['status' => 'ongoing']);
        $this->assertTrue($event->fresh()->isActive());
        $this->actingAs($judge)->postJson(route('judge.scores.autosave', $category), $cell)->assertOk();

        // Several events can be active at the same time.
        $this->assertSame(2, Event::where('status', 'ongoing')->count());
        $this->assertTrue(ActivityLog::where('action', 'tabulation')->where('event_id', $event->id)->exists());
    }

    public function test_score_changes_are_logged_and_rapid_typing_is_merged(): void
    {
        $judge = User::where('username', 'judge1')->first();
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $cell = ['contestant_id' => $talent->round->contestants()->first()->id, 'criterion_id' => $talent->criteria()->first()->id];

        $this->actingAs($judge);
        foreach (['3', '35', '35.5'] as $typed) {   // keystrokes on one cell
            $this->postJson(route('judge.scores.autosave', $talent), $cell + ['score' => $typed])->assertOk();
        }
        $this->assertSame(1, ScoreLog::count());
        $log = ScoreLog::first();
        $this->assertNull($log->old_score);
        $this->assertSame(35.5, $log->new_score);

        // A later correction is a separate entry.
        $this->travel(30)->seconds();
        $this->postJson(route('judge.scores.autosave', $talent), $cell + ['score' => '30'])->assertOk();
        $this->assertSame(2, ScoreLog::count());
        $this->assertSame(35.5, ScoreLog::latest('id')->first()->old_score);

        // Re-sending the same value logs nothing.
        $this->travel(30)->seconds();
        $this->postJson(route('judge.scores.autosave', $talent), $cell + ['score' => '30'])->assertOk();
        $this->assertSame(2, ScoreLog::count());
    }

    public function test_history_pages_render_with_logs(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'password']);
        $event = Event::first();
        $this->patch(route('admin.events.status', $event), ['status' => 'completed']);

        $this->get(route('admin.history'))->assertOk()->assertSee($event->name)->assertSee('Completed');
        $this->get(route('admin.history', ['tab' => 'scores']))->assertOk();
        $this->get(route('admin.history', ['tab' => 'activity']))->assertOk()
            ->assertSee('Logged in as Administrator')->assertSee('Tabulation set to Completed');
        $this->get(route('admin.history', ['tab' => 'activity', 'event' => $event->id, 'action' => 'tabulation']))->assertOk();
        $this->get(route('admin.events.judges', $event))->assertOk()->assertSee('Generate tabulator account');

        // Only admins can see history.
        $this->actingAs(User::where('username', 'tabulator')->first())->get(route('admin.history'))->assertForbidden();
    }
}
