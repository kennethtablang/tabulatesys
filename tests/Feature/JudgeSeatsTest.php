<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\Setting;
use App\Models\User;
use App\Services\TabulationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JudgeSeatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
        $this->seed(DatabaseSeeder::class);
    }

    protected function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    protected function seats(Event $event): array
    {
        return $event->judges()->get()->mapWithKeys(fn ($j) => [$j->pivot->seat => $j->username])->all();
    }

    public function test_admin_arranges_seats_and_every_view_follows_them(): void
    {
        $event = Event::where('name', 'Battle of the Voices 2026')->firstOrFail();   // judges 1-4
        $this->actingAs($this->admin());
        $this->get(route('admin.events.judges', $event))->assertOk()->assertSee('Seating arrangement')->assertSee('Seat 4');

        // Seat plan: judge3, judge1, judge4, judge2.
        $ids = fn (...$names) => array_map(fn ($n) => User::where('username', $n)->value('id'), $names);
        $this->put(route('admin.events.judges.seats', $event), ['order' => $ids('judge3', 'judge1', 'judge4', 'judge2')])->assertRedirect();
        $this->assertSame([1 => 'judge3', 2 => 'judge1', 3 => 'judge4', 4 => 'judge2'], $this->seats($event));

        // Move up / down.
        $judge2 = User::where('username', 'judge2')->first();
        $this->post(route('admin.events.judges.move', [$event, $judge2]), ['direction' => 'up'])->assertRedirect();
        $this->assertSame([1 => 'judge3', 2 => 'judge1', 3 => 'judge2', 4 => 'judge4'], $this->seats($event));

        // Results and exports list judges in seat order.
        $round = $event->rounds()->first();
        $order = app(TabulationService::class)->roundResults($round)['judges']->pluck('username')->all();
        $this->assertSame(['judge3', 'judge1', 'judge2', 'judge4'], $order);
        $html = $this->get(route('admin.events.monitor', $event))->assertOk()->getContent();
        $this->assertTrue(strpos($html, 'Judge Ana Cruz') < strpos($html, 'Judge Maria Santos'), 'seat 1 (judge3) comes first on the monitor');
        $this->get(route('admin.rounds.print', $round))->assertSee('Judge · Seat 1');
        $this->get(route('admin.rounds.export.pdf', $round))->assertOk();

        // The judge sees their seat.
        $category = Category::where('event_id', $event->id)->firstOrFail();
        $this->actingAs(User::where('username', 'judge3')->first())->get(route('judge.sheet', $category))->assertSee('Seat 1');
    }

    public function test_new_judges_take_the_next_seat_and_removing_one_closes_the_gap(): void
    {
        $event = Event::where('name', 'Battle of the Voices 2026')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.events.judges.generate', $event), ['names' => 'Guest Judge'])->assertRedirect();
        $seats = $this->seats($event);
        $this->assertCount(5, $seats);
        $this->assertSame('ev'.$event->id.'-judge1', $seats[5]);

        $this->delete(route('admin.events.judges.detach', [$event, User::where('username', 'judge2')->first()]));
        $this->assertSame([1, 2, 3, 4], array_keys($this->seats($event)));
    }

    public function test_times_are_shown_in_philippine_time(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('+08:00', now()->format('P'));
    }
}
