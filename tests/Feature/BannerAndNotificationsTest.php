<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Event;
use App\Models\Logo;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BannerAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach (Event::whereNotNull('banner')->get() as $e) {
            File::delete(public_path('uploads/'.$e->banner));
        }
        foreach (Logo::all() as $logo) {
            File::delete(public_path('uploads/'.$logo->file));
        }
        Setting::flushCache();
        parent::tearDown();
    }

    protected function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    protected function eventPayload(Event $event, array $extra = []): array
    {
        return array_merge(['name' => $event->name, 'type' => $event->type, 'scoring_method' => $event->scoring_method, 'status' => $event->status], $extra);
    }

    public function test_admin_adds_replaces_and_removes_an_event_banner(): void
    {
        $event = Event::where('name', 'Mr. & Ms. Foundation Day 2026')->firstOrFail();
        $this->actingAs($this->admin());
        $this->get(route('admin.events.edit', $event))->assertOk()->assertSee('Event banner')->assertSee('multipart/form-data', false);

        $png = fn () => UploadedFile::fake()->createWithContent('banner.png', base64_decode(self::PNG));
        $this->put(route('admin.events.update', $event), $this->eventPayload($event, ['banner' => $png()]))->assertRedirect();
        $first = $event->fresh()->banner;
        $this->assertNotNull($first);
        $this->assertFileExists(public_path('uploads/'.$first));

        // Judges and admin pages show it.
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $url = $event->fresh()->bannerUrl();
        $this->get(route('admin.events.show', $event))->assertSee($url);
        $this->get(route('admin.events.index'))->assertSee($url);
        $judge = User::where('username', 'judge1')->first();
        $this->actingAs($judge)->get(route('judge.dashboard'))->assertSee($url);
        $this->get(route('judge.event', $event))->assertSee($url);
        $this->get(route('judge.sheet', $talent))->assertSee('event-banner-slim', false)->assertSee($url);

        // Replace: the old file is deleted.
        $this->actingAs($this->admin())->put(route('admin.events.update', $event), $this->eventPayload($event, ['banner' => $png()]));
        $second = $event->fresh()->banner;
        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist(public_path('uploads/'.$first));

        // Editing other details keeps the banner; "remove" deletes it.
        $this->put(route('admin.events.update', $event), $this->eventPayload($event, ['venue' => 'Gym']));
        $this->assertSame($second, $event->fresh()->banner);
        $this->put(route('admin.events.update', $event), $this->eventPayload($event, ['remove_banner' => 1]));
        $this->assertNull($event->fresh()->banner);
        $this->assertFileDoesNotExist(public_path('uploads/'.$second));

        // Not an image → rejected.
        $this->put(route('admin.events.update', $event), $this->eventPayload($event, ['banner' => UploadedFile::fake()->createWithContent('x.svg', '<svg/>')]))
            ->assertSessionHasErrors('banner');
    }

    public function test_bell_tracks_judge_actions(): void
    {
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $judge = User::where('username', 'judge1')->first();

        // Admin opens the bell: nothing unread yet from judges.
        $this->actingAs($this->admin())->getJson(route('admin.notifications'))->assertOk()->assertJson(['unread' => 0]);

        // A judge logs in, starts scoring and submits.
        $this->post('/logout');
        $this->post('/login', ['username' => 'judge1', 'password' => 'password']);
        foreach ($talent->round->contestants as $c) {
            foreach ($talent->criteria as $k) {
                $this->postJson(route('judge.scores.autosave', $talent), ['contestant_id' => $c->id, 'criterion_id' => $k->id, 'score' => 20])->assertOk();
            }
        }
        $this->post(route('judge.scores.submit', $talent))->assertRedirect();
        $this->assertSame(1, ActivityLog::where('action', 'scoring')->where('user_id', $judge->id)->count(), 'logged once per category');
        $this->post('/login', ['username' => 'judge1', 'password' => 'wrong']);   // still logged in → ignored by guest middleware
        $this->post('/logout');
        $this->post('/login', ['username' => 'judge1', 'password' => 'wrong']);    // failed login

        $feed = $this->actingAs($this->admin())->getJson(route('admin.notifications'))->assertOk()->json();
        $this->assertGreaterThanOrEqual(5, $feed['unread']);
        $texts = collect($feed['items'])->map(fn ($i) => $i['who'].' '.$i['text'])->implode(' | ');
        $this->assertStringContainsString('Judge Maria Santos logged in', $texts);
        $this->assertStringContainsString('started scoring "Talent"', $texts);
        $this->assertStringContainsString('submitted final scores for "Talent"', $texts);
        $this->assertStringContainsString('failed login attempt', $texts);

        // Opening the bell marks everything read.
        $this->postJson(route('admin.notifications.read'))->assertOk();
        $this->getJson(route('admin.notifications'))->assertJson(['unread' => 0]);

        // The bell is on admin pages, not on judges' screens.
        $this->get(route('admin.dashboard'))->assertSee('id="notif-bell"', false);
        $this->actingAs($judge)->get(route('judge.dashboard'))->assertDontSee('id="notif-bell"', false);
        $this->getJson(route('admin.notifications'))->assertForbidden();
    }

    public function test_tabulator_bell_only_shows_their_events_judges(): void
    {
        $singing = Event::where('name', 'Battle of the Voices 2026')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.events.tabulators.store', $singing));
        $tab = User::where('event_id', $singing->id)->firstOrFail();

        $talent = Category::where('name', 'Talent')->firstOrFail();   // pageant, not the singing contest
        $perf = Category::where('event_id', $singing->id)->firstOrFail();
        $judge = User::where('username', 'judge1')->first();           // judges both
        $this->actingAs($judge)->postJson(route('judge.scores.autosave', $talent), ['contestant_id' => $talent->round->contestants()->first()->id, 'criterion_id' => $talent->criteria()->first()->id, 'score' => 10])->assertOk();
        $this->postJson(route('judge.scores.autosave', $perf), ['contestant_id' => $perf->round->contestants()->first()->id, 'criterion_id' => $perf->criteria()->first()->id, 'score' => 5])->assertOk();

        $texts = collect($this->actingAs($tab)->getJson(route('admin.notifications'))->assertOk()->json('items'))->pluck('text')->implode(' | ');
        $this->assertStringContainsString('"Performance"', $texts);
        $this->assertStringNotContainsString('"Talent"', $texts);
    }

    public function test_large_uploads_are_shrunk(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD not available');
        }
        $img = imagecreatetruecolor(2400, 1200);
        imagefill($img, 0, 0, imagecolorallocate($img, 20, 120, 200));
        $tmp = tempnam(sys_get_temp_dir(), 'png').'.png';
        imagepng($img, $tmp);
        imagedestroy($img);

        $this->actingAs($this->admin())->post(route('admin.logos.store'), [
            'name' => 'Big', 'logo' => new UploadedFile($tmp, 'big.png', 'image/png', null, true), 'show_on_all' => 1,
        ])->assertSessionHasNoErrors();
        [$w, $h] = getimagesize(public_path('uploads/'.Logo::where('name', 'Big')->value('file')));
        $this->assertSame([800, 400], [$w, $h]);
        @unlink($tmp);
    }
}
