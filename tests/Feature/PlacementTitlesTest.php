<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\RoundTitle;
use App\Models\Setting;
use App\Models\User;
use App\Services\TabulationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class PlacementTitlesTest extends TestCase
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

    protected function prelim()
    {
        return Event::where('name', 'Mr. & Ms. Foundation Day 2026')->firstOrFail()->rounds()->orderBy('sequence')->first();
    }

    public function test_admin_sets_placement_titles_with_division_overrides(): void
    {
        $round = $this->prelim();
        $this->actingAs($this->admin());
        $this->get(route('admin.rounds.titles', $round))->assertOk()->assertSee('Placement Titles')->assertSee('Mr only')->assertSee('Quick fill');
        $this->get(route('admin.events.setup', $round->event))->assertSee(route('admin.rounds.titles', $round));

        $this->put(route('admin.rounds.titles.update', $round), ['titles' => [
            1 => ['_all' => 'Winner', 'Mr' => 'Mr. Foundation Day 2026', 'Ms' => 'Ms. Foundation Day 2026'],
            2 => ['_all' => '1st Runner-Up', 'Mr' => '', 'Ms' => ''],
            3 => ['_all' => '2nd Runner-Up'],
            4 => ['_all' => ''],
        ]])->assertRedirect(route('admin.rounds.titles', $round));
        $this->assertSame(5, RoundTitle::where('round_id', $round->id)->count());
        $this->assertSame(3, $round->fresh()->titledPlaces());

        $results = app(TabulationService::class)->roundResults($round->fresh());
        $mr = array_values($results['divisions']['Mr']['overall']);
        $ms = array_values($results['divisions']['Ms']['overall']);
        $this->assertSame('Mr. Foundation Day 2026', $mr[0]['title']);
        $this->assertSame('Ms. Foundation Day 2026', $ms[0]['title']);
        $this->assertSame('1st Runner-Up', $mr[1]['title']);
        $this->assertSame('2nd Runner-Up', $ms[2]['title']);
        $this->assertNull($mr[3]['title']);

        // Shown on live results, print, PDF, Excel.
        $this->get(route('admin.events.results', [$round->event, 'round' => $round->id]))->assertSee('Mr. Foundation Day 2026')->assertSee('placement-title', false);
        $this->get(route('admin.rounds.print', $round))->assertSee('Ms. Foundation Day 2026');
        $this->get(route('admin.rounds.export.pdf', $round))->assertOk();

        $file = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($file, $this->get(route('admin.rounds.export.excel', $round))->getContent());
        $zip = new ZipArchive;
        $zip->open($file);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('>Title<', $sheet);
        $this->assertStringContainsString('Mr. Foundation Day 2026', $sheet);
        $zip->close();
        @unlink($file);

        // Tabulators can't edit titles.
        $this->actingAs(User::where('username', 'tabulator')->first())->get(route('admin.rounds.titles', $round))->assertForbidden();
    }

    public function test_top_n_view_shows_any_number_of_finalists(): void
    {
        $round = $this->prelim();
        $event = $round->event;
        $this->actingAs($this->admin());

        // Default: top 3.
        $this->get(route('admin.events.results', [$event, 'round' => $round->id, 'view' => 'podium']))
            ->assertOk()->assertSee('Top 3 ·', false)->assertDontSee('viz-finalist"', false);

        // Top 5: three on the podium, two finalist cards per division.
        $html = $this->get(route('admin.events.results', [$event, 'round' => $round->id, 'view' => 'podium', 'top' => 5]))
            ->assertOk()->assertSee('Top 5 ·', false)->getContent();
        $this->assertSame(4, substr_count($html, 'class="viz-finalist"'), '2 extra finalists in each of 2 divisions');
        // The live refresh keeps the chosen number (it used to fall back to the default).
        $this->assertStringContainsString('top=5', $html);

        // Titles for 5 places make "Top 5" the default.
        RoundTitle::create(['round_id' => $round->id, 'rank' => 5, 'title' => '4th Runner-Up']);
        $this->get(route('admin.events.results', [$event, 'round' => $round->id, 'view' => 'podium']))->assertSee('Top 5 ·', false)->assertSee('4th Runner-Up');

        // Out-of-range numbers are clamped.
        $this->get(route('admin.events.results', [$event, 'round' => $round->id, 'view' => 'podium', 'top' => 999]))->assertOk()->assertSee('Top 50 ·', false);
    }
}
