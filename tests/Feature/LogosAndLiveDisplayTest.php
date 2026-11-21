<?php

namespace Tests\Feature;

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

class LogosAndLiveDisplayTest extends TestCase
{
    use RefreshDatabase;

    /** 1×1 transparent PNG (no GD needed). */
    protected const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected array $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
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

    protected function upload(string $name, bool $everyEvent = false): Logo
    {
        $file = UploadedFile::fake()->createWithContent(strtolower(str_replace(' ', '-', $name)).'.png', base64_decode(self::PNG));
        $this->actingAs($this->admin())->post(route('admin.logos.store'), array_filter([
            'name' => $name, 'logo' => $file, 'show_on_all' => $everyEvent ? 1 : null,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        return Logo::where('name', $name)->firstOrFail();
    }

    public function test_admin_adds_many_logos_and_judges_see_them_per_event(): void
    {
        $school = $this->upload('ABC University', everyEvent: true);
        $lgu = $this->upload('Municipality of San Jose');
        $sponsor = $this->upload('Sponsor Co');
        $this->assertFileExists(public_path('uploads/'.$school->file));

        $pageant = Event::where('name', 'Mr. & Ms. Foundation Day 2026')->firstOrFail();
        $singing = Event::where('name', 'Battle of the Voices 2026')->firstOrFail();

        // LGU + sponsor only on the pageant (picked from the event form).
        $this->put(route('admin.events.update', $pageant), [
            'name' => $pageant->name, 'type' => $pageant->type, 'scoring_method' => $pageant->scoring_method,
            'status' => $pageant->status, 'logos' => [$lgu->id, $sponsor->id],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(['ABC University', 'Municipality of San Jose', 'Sponsor Co'], $pageant->displayLogos()->pluck('name')->all());
        $this->assertSame(['ABC University'], $singing->displayLogos()->pluck('name')->all());

        $judge = User::where('username', 'judge1')->first();
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $this->actingAs($judge)->get(route('judge.sheet', $talent))->assertOk()
            ->assertSee($school->url())->assertSee($lgu->url())->assertSee($sponsor->url());
        $this->get(route('judge.event', $pageant))->assertOk()->assertSee($lgu->url());
        $this->get(route('judge.dashboard'))->assertOk()->assertSee($school->url());

        // Printed results and the login page.
        $this->actingAs($this->admin())->get(route('admin.rounds.print', $pageant->rounds()->first()))->assertSee($sponsor->url());
        $this->post('/logout');
        $this->get('/login')->assertSee($school->url())->assertDontSee($sponsor->url());

        // Deleting a logo removes its file.
        $this->actingAs($this->admin())->delete(route('admin.logos.destroy', $sponsor))->assertRedirect();
        $this->assertFileDoesNotExist(public_path('uploads/'.$sponsor->file));
        $this->get(route('admin.logos.index'))->assertRedirect();
        $this->get(route('admin.settings'))->assertOk()->assertSee('Logos: school, organization, LGU')->assertSee('Municipality of San Jose');
    }

    public function test_only_images_can_be_uploaded_as_logos(): void
    {
        $this->actingAs($this->admin())->post(route('admin.logos.store'), [
            'name' => 'Bad', 'logo' => UploadedFile::fake()->createWithContent('evil.svg', '<svg onload="alert(1)"></svg>'),
        ])->assertSessionHasErrors('logo');
        $this->assertSame(0, Logo::count());
    }

    public function test_judge_screens_learn_about_admin_changes_and_refreshes(): void
    {
        $judge = User::where('username', 'judge1')->first();
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $event = $talent->event;
        $other = Event::where('name', 'Battle of the Voices 2026')->firstOrFail();

        $ping = fn () => $this->actingAs($judge)->getJson(route('judge.ping', ['event' => $event->id]))->assertOk()->json('token');
        $t0 = $ping();
        $this->assertSame($t0, $ping());   // stable while nothing changes

        // Admin saves settings → token changes.
        $this->actingAs($this->admin())->put(route('admin.settings.update'), [
            'app_name' => 'TMS', 'sheet_layout' => 'focus', 'photo_size' => 'medium', 'contestant_order' => 'number',
            'input_size' => 'normal', 'logo_size' => 'large', 'allow_switch' => 1, 'accent' => '#1ab394',
        ])->assertRedirect();
        $t1 = $ping();
        $this->assertNotSame($t0, $t1);

        // Refreshing another event's judges doesn't disturb this event's screens; this event's refresh does.
        $this->actingAs($this->admin())->post(route('admin.judges.refresh'), ['event' => $other->id])->assertRedirect();
        $this->assertSame($t1, $ping());
        $this->actingAs($this->admin())->post(route('admin.judges.refresh'), ['event' => $event->id]);
        $t2 = $ping();
        $this->assertNotSame($t1, $t2);
        $this->actingAs($this->admin())->post(route('admin.judges.refresh'));   // all events
        $this->assertNotSame($t2, $ping());

        // The page embeds the same token the ping returns, so it only reloads on real changes.
        $html = $this->actingAs($judge)->get(route('judge.sheet', $talent))->getContent();
        $this->assertStringContainsString('token: "'.$ping().'"', $html);

        // The monitor shows the judge as online.
        $this->actingAs($this->admin())->get(route('admin.events.monitor', $event))->assertSee('Online');
    }

    public function test_settings_change_resets_a_judges_own_layout_pick(): void
    {
        $judge = User::where('username', 'judge1')->first();
        $talent = Category::where('name', 'Talent')->firstOrFail();

        $this->actingAs($judge)->get(route('judge.sheet', [$talent, 'layout' => 'cards']))->assertSee('sheet-layout-cards', false);
        $this->get(route('judge.sheet', $talent))->assertSee('sheet-layout-cards', false);

        $this->actingAs($this->admin())->put(route('admin.settings.update'), [
            'app_name' => 'TMS', 'sheet_layout' => 'criteria', 'photo_size' => 'medium', 'contestant_order' => 'number',
            'input_size' => 'normal', 'logo_size' => 'medium', 'allow_switch' => 1, 'accent' => '#1ab394',
        ]);
        $this->actingAs($judge)->get(route('judge.sheet', $talent))->assertSee('sheet-layout-criteria', false);
    }

    public function test_who_may_refresh_judges(): void
    {
        $event = Event::where('name', 'Battle of the Voices 2026')->firstOrFail();
        $other = Event::where('name', 'Mr. & Ms. Foundation Day 2026')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.events.tabulators.store', $event));
        $tab = User::where('event_id', $event->id)->firstOrFail();

        $this->actingAs($tab)->post(route('admin.judges.refresh'), ['event' => $event->id])->assertRedirect();
        $this->actingAs($tab)->post(route('admin.judges.refresh'), ['event' => $other->id])->assertForbidden();
        $this->actingAs($tab)->post(route('admin.judges.refresh'))->assertForbidden();
        $this->actingAs(User::where('username', 'judge1')->first())->post(route('admin.judges.refresh'))->assertForbidden();
    }
    public function test_logos_are_managed_in_settings_and_editable(): void
    {
        $this->actingAs($this->admin());
        $file = UploadedFile::fake()->createWithContent('lgu.png', base64_decode(self::PNG));
        $this->post(route('admin.logos.store'), ['name' => 'LGU of Alaminos', 'type' => 'lgu', 'logo' => $file, 'show_on_all' => 1])->assertRedirect();
        $logo = Logo::where('name', 'LGU of Alaminos')->firstOrFail();
        $this->assertSame('LGU', $logo->typeLabel());

        $this->get(route('admin.settings'))->assertOk()->assertSee('LGU of Alaminos')->assertSee($logo->url());

        // Edit: rename, change type, restrict to one event, replace the image.
        $oldFile = $logo->file;
        $event = Event::where('name', 'Battle of the Voices 2026')->firstOrFail();
        $this->put(route('admin.logos.update', $logo), [
            'name' => 'City of Alaminos', 'type' => 'organization', 'sort_order' => 3, 'events' => [$event->id],
            'logo' => UploadedFile::fake()->createWithContent('new.png', base64_decode(self::PNG)),
        ])->assertRedirect();
        $logo->refresh();
        $this->assertSame(['City of Alaminos', 'organization', 3, false], [$logo->name, $logo->type, $logo->sort_order, $logo->show_on_all]);
        $this->assertNotSame($oldFile, $logo->file);
        $this->assertFileDoesNotExist(public_path('uploads/'.$oldFile));
        $this->assertFileExists(public_path('uploads/'.$logo->file));
        $this->assertSame([$event->id], $logo->events()->pluck('events.id')->all());

        // Display options.
        $this->put(route('admin.logos.options'), ['heading_height' => 140, 'logos_on_print' => 1])->assertRedirect();
        $this->assertSame(140, Logo::headingHeight());
        $this->put(route('admin.logos.options'), ['heading_height' => 999])->assertSessionHasErrors('heading_height');
        $this->put(route('admin.logos.update', $logo), ['name' => $logo->name, 'sort_order' => 3, 'scale' => 150, 'events' => [$event->id]])->assertRedirect();
        $this->assertSame(150, $logo->fresh()->scale);
        $logo->refresh();
        $this->assertSame('0', Setting::get('logos.on_login'));

        // It shows on the right of the judges' screen heading, for that event only.
        $judge = User::where('username', 'judge1')->first();
        $sheet = Category::where('event_id', $event->id)->firstOrFail();
        $html = $this->actingAs($judge)->get(route('judge.sheet', $sheet))->assertOk()->getContent();
        $this->assertStringContainsString('logo-band-heading', $html);
        $this->assertStringContainsString('--heading-logo-h: 140px', $html);
        $this->assertStringContainsString('--logo-scale: 1.5', $html);
        $this->assertStringContainsString($logo->url(), $html);
        $other = Category::where('name', 'Talent')->firstOrFail();
        $this->get(route('judge.sheet', $other))->assertDontSee($logo->url());
    }
    public function test_uploaded_logos_show_on_judge_screens_only(): void
    {
        $logo = $this->upload('ABC University', everyEvent: true);
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $event = $talent->event;

        // Admin panel: no logos in the page heading.
        $this->actingAs($this->admin());
        foreach ([route('admin.dashboard'), route('admin.events.show', $event), route('admin.events.results', $event), route('admin.events.monitor', $event)] as $url) {
            $this->get($url)->assertOk()->assertDontSee('logo-band-heading', false);
        }
        // ...except the "Preview judge view", which shows exactly what judges see.
        $this->get(route('admin.settings.preview', ['category' => $talent->id]))->assertSee('logo-band-heading', false)->assertSee($logo->url());

        // Tabulator panel: no logos either.
        $this->actingAs(User::where('username', 'tabulator')->first())->get(route('admin.events.results', $event))->assertDontSee('logo-band-heading', false);

        // Judges: logos on the right of the heading.
        $judge = User::where('username', 'judge1')->first();
        $this->actingAs($judge)->get(route('judge.sheet', $talent))->assertSee('logo-band-heading', false)->assertSee($logo->url());
        $this->get(route('judge.event', $event))->assertSee('logo-band-heading', false);
        $this->get(route('judge.dashboard'))->assertSee('logo-band-heading', false);
    }
}
