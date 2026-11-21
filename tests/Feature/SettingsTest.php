<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Support\JudgeDisplay;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Setting::flushCache();
        parent::tearDown();
    }

    protected function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    protected function settings(array $overrides = []): array
    {
        return array_merge([
            'app_name' => 'Pageant Tabulator', 'organization' => 'ABC University',
            'sheet_layout' => 'cards', 'photo_size' => 'large', 'contestant_order' => 'name', 'input_size' => 'large', 'logo_size' => 'medium', 'accent' => '#1ab394',
            'show_photos' => 1, 'show_names' => 1, 'show_details' => 1, 'show_totals' => 1, 'show_my_rank' => 1,
            'show_criteria_guide' => 1, 'allow_switch' => 1,
        ], $overrides);
    }

    public function test_admin_saves_settings_and_judges_get_the_layout(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings'))->assertOk()->assertSee('One at a Time')->assertSee('By Criterion');
        $this->put(route('admin.settings.update'), $this->settings())->assertRedirect(route('admin.settings'));

        $this->assertSame('cards', JudgeDisplay::defaults()['sheet_layout']);
        $this->assertTrue(JudgeDisplay::defaults()['show_my_rank']);
        $this->assertSame('Pageant Tabulator', Setting::get('general.app_name'));

        $talent = Category::where('name', 'Talent')->firstOrFail();
        $this->actingAs(User::where('username', 'judge1')->first())
            ->get(route('judge.sheet', $talent))->assertOk()
            ->assertSee('sheet-layout-cards', false)
            ->assertSee('contestant-rank', false)
            ->assertSee('View contestants as');
    }

    public function test_every_layout_renders_with_working_score_boxes(): void
    {
        $talent = Category::with('criteria', 'round.contestants')->where('name', 'Talent')->firstOrFail();
        $boxes = $talent->criteria->count() * $talent->round->contestants->count();
        $judge = User::where('username', 'judge1')->first();

        foreach (array_keys(JudgeDisplay::LAYOUTS) as $layout) {
            $html = $this->actingAs($judge)->get(route('judge.sheet', [$talent, 'layout' => $layout]))
                ->assertOk()->assertSee('sheet-layout-'.$layout, false)->getContent();
            $this->assertSame($boxes, substr_count($html, 'class="form-control score-input'), "Layout $layout should render every score box once");
        }

        // The judge's pick is remembered for the event.
        $this->get(route('judge.sheet', $talent))->assertSee('sheet-layout-compact', false);
    }

    public function test_blind_judging_hides_names_and_switching_can_be_disabled(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.update'), $this->settings([
            'sheet_layout' => 'focus', 'show_names' => 0, 'allow_switch' => 0,
        ]));

        $talent = Category::where('name', 'Talent')->firstOrFail();
        $name = $talent->round->contestants()->first()->name;
        $this->actingAs(User::where('username', 'judge1')->first())
            ->get(route('judge.sheet', [$talent, 'layout' => 'table']))->assertOk()
            ->assertSee('sheet-layout-focus', false)      // can't switch
            ->assertDontSee($name)
            ->assertSee('Contestant No.')
            ->assertDontSee('View contestants as');
    }

    public function test_event_layout_overrides_the_default_and_admin_can_preview(): void
    {
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $event = $talent->event;
        $event->update(['sheet_layout' => 'criteria']);

        $this->actingAs(User::where('username', 'judge1')->first())
            ->get(route('judge.sheet', $talent))->assertSee('sheet-layout-criteria', false);

        $this->actingAs($this->admin())->get(route('admin.settings.preview', ['category' => $talent->id, 'layout' => 'focus']))
            ->assertOk()->assertSee('Preview')->assertSee('sheet-layout-focus', false);
        $this->get(route('admin.events.edit', $event))->assertOk()->assertSee('Judge score-sheet layout');

        // Settings are admin-only.
        $this->actingAs(User::where('username', 'tabulator')->first())->get(route('admin.settings'))->assertForbidden();
    }
    public function test_admin_changes_the_highlight_colour_for_everyone(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings'))->assertOk()->assertSee('Appearance: highlight colour')->assertSee('Royal blue');
        $this->put(route('admin.settings.update'), $this->settings(['accent' => '#A4243B']))->assertRedirect();
        $this->assertSame('#a4243b', \App\Support\Theme::accent());

        // Every page (admin, judge, login) carries the new colour.
        $this->get(route('admin.dashboard'))->assertSee('--accent: #a4243b;', false)->assertSee('css/accent.css', false);
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $this->actingAs(User::where('username', 'judge1')->first())->get(route('judge.sheet', $talent))->assertSee('--accent: #a4243b;', false);
        $this->post('/logout');
        $this->get('/login')->assertSee('--accent: #a4243b;', false);

        // Not a colour → rejected, old colour kept.
        $this->actingAs($this->admin())->put(route('admin.settings.update'), $this->settings(['accent' => 'red; }</style><script>']))->assertSessionHasErrors('accent');
        $this->assertSame('#a4243b', \App\Support\Theme::accent());
    }

    public function test_highlight_text_stays_readable(): void
    {
        $theme = \App\Support\Theme::class;
        foreach (array_merge(array_keys($theme::PRESETS), ['#ffe680', '#000000', '#ffffff', '#00ff00']) as $hex) {
            $v = $theme::vars($hex);
            $L = $v['light'];
            $D = $v['dark'];
            // Text on buttons/badges/selected items: >= 4.5:1 in both themes.
            $this->assertGreaterThanOrEqual(4.5, $theme::contrast($L['--accent-on'], $L['--accent-fill']), "light button text on $hex");
            $this->assertGreaterThanOrEqual(4.5, $theme::contrast($D['--accent-on'], $D['--accent-fill']), "dark button text on $hex");
            // Selected items stand out from the dark page (the "blends in" bug).
            $this->assertGreaterThanOrEqual(3.0, $theme::contrast($D['--accent-fill'], $theme::DARK_SURFACE), "dark fill vs page for $hex");
            // Highlighted text on the page.
            $this->assertGreaterThanOrEqual(4.5, $theme::contrast($L['--accent-ink'], $theme::LIGHT_SURFACE), "light ink for $hex");
            $this->assertGreaterThanOrEqual(4.5, $theme::contrast($D['--accent-ink'], $theme::DARK_SURFACE), "dark ink for $hex");
        }
        // Gold keeps its colour and gets dark text; navy is lifted in dark mode so it doesn't vanish.
        $this->assertSame(['#d4a106', '#16181b'], [$theme::vars('#d4a106')['light']['--accent-fill'], $theme::vars('#d4a106')['light']['--accent-on']]);
        $this->assertNotSame('#23395d', $theme::vars('#23395d')['dark']['--accent-fill']);
    }

    public function test_judges_have_no_sidebar(): void
    {
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $this->actingAs(User::where('username', 'judge1')->first());
        foreach ([route('judge.dashboard'), route('judge.event', $talent->event), route('judge.sheet', $talent)] as $url) {
            $this->get($url)->assertOk()->assertDontSee('navbar-static-side', false)->assertSee('judge-mode', false)->assertSee('My Events');
        }
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertSee('navbar-static-side', false);
        $this->actingAs(User::where('username', 'tabulator')->first())->get(route('tabulator.dashboard'))->assertSee('navbar-static-side', false);
    }
    public function test_page_layout_structure_is_intact(): void
    {
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $pages = [
            [$this->admin(), route('admin.dashboard')],
            [$this->admin(), route('admin.events.results', $talent->event)],
            [User::where('username', 'judge1')->first(), route('judge.sheet', $talent)],
        ];
        foreach ($pages as [$user, $url]) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $dom = new \DOMDocument;
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            $wrapper = $xpath->query('//*[@id="page-wrapper"]')->item(0);
            $this->assertNotNull($wrapper, $url);
            foreach (['page-heading', 'wrapper-content', 'footer'] as $class) {
                $this->assertSame(1, $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]', $wrapper)->length, "$class inside #page-wrapper on $url");
            }
        }
    }
    public function test_one_at_a_time_shows_a_large_portrait_photo(): void
    {
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $this->actingAs(User::where('username', 'judge1')->first())
            ->get(route('judge.sheet', [$talent, 'layout' => 'focus']))->assertOk()
            ->assertSee('sheet-photo-portrait', false)
            ->assertDontSee('sheet-photo-xl', false);
    }
}
