<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\Logo;
use App\Models\Score;
use App\Models\Setting;
use App\Models\User;
use App\Support\ScreenLock;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class ScreenLockAndExportLogosTest extends TestCase
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

    public function test_locking_one_judge_blocks_their_scoring_until_unlocked(): void
    {
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $event = $talent->event;
        $judge = User::where('username', 'judge1')->first();
        $other = User::where('username', 'judge2')->first();
        $cell = ['contestant_id' => $talent->round->contestants()->first()->id, 'criterion_id' => $talent->criteria()->first()->id, 'score' => 30];

        $this->actingAs($this->admin())->post(route('admin.judges.lock'), ['scope' => 'judge', 'judge' => $judge->id, 'event' => $event->id, 'lock' => 1])->assertRedirect();

        // The judge's screen learns it is locked, and the server refuses scores.
        $this->actingAs($judge)->getJson(route('judge.ping', ['event' => $event->id]))->assertJson(['locked' => true]);
        $this->postJson(route('judge.scores.autosave', $talent), $cell)->assertStatus(423)->assertJson(['screen_locked' => true]);
        $this->post(route('judge.scores.save', $talent), ['scores' => []])->assertSessionHas('error');
        $this->get(route('judge.sheet', $talent))->assertOk()->assertSee('screen-lock show', false)->assertSee('Screen locked');
        $this->assertSame(0, Score::where('judge_id', $judge->id)->where('category_id', $talent->id)->count());

        // Other judges keep working.
        $this->actingAs($other)->postJson(route('judge.scores.autosave', $talent), $cell)->assertOk();

        // Unlock → scoring works again.
        $this->actingAs($this->admin())->post(route('admin.judges.lock'), ['scope' => 'judge', 'judge' => $judge->id, 'event' => $event->id, 'lock' => 0]);
        $this->actingAs($judge)->getJson(route('judge.ping', ['event' => $event->id]))->assertJson(['locked' => false]);
        $this->postJson(route('judge.scores.autosave', $talent), $cell)->assertOk();
    }

    public function test_lock_all_judges_of_an_event_and_of_every_event(): void
    {
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $pageant = $talent->event;
        $singing = Event::where('name', 'Battle of the Voices 2026')->firstOrFail();
        $judge = User::where('username', 'judge1')->first();   // judges both events

        $this->actingAs($this->admin())->post(route('admin.judges.lock'), ['scope' => 'event', 'event' => $pageant->id, 'lock' => 1]);
        $this->assertTrue(ScreenLock::isLocked($judge, $pageant->id));
        $this->assertFalse(ScreenLock::isLocked($judge, $singing->id), 'other event unaffected');
        $this->get(route('admin.events.monitor', $pageant))->assertSee('Unlock all judge screens')->assertSee('Locked');

        $this->post(route('admin.judges.lock'), ['scope' => 'event', 'event' => $pageant->id, 'lock' => 0]);
        $this->post(route('admin.judges.lock'), ['scope' => 'all', 'lock' => 1]);
        $this->assertTrue(ScreenLock::isLocked($judge, $singing->id));
        $this->assertTrue(ScreenLock::isLocked($judge));
        $this->get(route('admin.settings'))->assertSee('Unlock all judge screens');
        $this->post(route('admin.judges.lock'), ['scope' => 'all', 'lock' => 0]);
        $this->assertFalse(ScreenLock::isLocked($judge, $singing->id));
    }

    public function test_who_may_lock_screens(): void
    {
        $own = Event::where('name', 'Battle of the Voices 2026')->firstOrFail();
        $other = Event::where('name', 'Mr. & Ms. Foundation Day 2026')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.events.tabulators.store', $own));
        $tab = User::where('event_id', $own->id)->firstOrFail();
        $judge4 = User::where('username', 'judge4')->first();   // judges only the singing contest

        $this->actingAs($tab)->post(route('admin.judges.lock'), ['scope' => 'event', 'event' => $own->id, 'lock' => 1])->assertRedirect();
        $this->actingAs($tab)->post(route('admin.judges.lock'), ['scope' => 'judge', 'judge' => $judge4->id, 'event' => $own->id, 'lock' => 1])->assertRedirect();
        $this->actingAs($tab)->post(route('admin.judges.lock'), ['scope' => 'event', 'event' => $other->id, 'lock' => 1])->assertForbidden();
        $this->actingAs($tab)->post(route('admin.judges.lock'), ['scope' => 'judge', 'judge' => $judge4->id, 'event' => $other->id, 'lock' => 1])->assertForbidden();
        $this->actingAs($tab)->post(route('admin.judges.lock'), ['scope' => 'all', 'lock' => 1])->assertForbidden();
        $this->actingAs($judge4)->post(route('admin.judges.lock'), ['scope' => 'all', 'lock' => 1])->assertForbidden();
    }

    public function test_logout_asks_for_confirmation(): void
    {
        $js = file_get_contents(public_path('js/tabulation.js'));
        $this->assertStringContainsString('Are you really sure you want to logout?', $js);
    }

    public function test_logos_are_in_the_excel_and_pdf_exports(): void
    {
        $this->actingAs($this->admin());
        foreach (['ABC School', 'City LGU'] as $name) {
            $this->post(route('admin.logos.store'), [
                'name' => $name, 'logo' => UploadedFile::fake()->createWithContent(str_replace(' ', '', $name).'.png', base64_decode(self::PNG)), 'show_on_all' => 1,
            ])->assertSessionHasNoErrors();
        }
        $round = Event::where('name', 'Mr. & Ms. Foundation Day 2026')->firstOrFail()->rounds()->first();

        // Excel: images embedded with drawings on the sheets, and still a valid workbook.
        $file = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($file, $this->get(route('admin.rounds.export.excel', $round))->assertOk()->getContent());
        $zip = new ZipArchive;
        $zip->open($file);
        $this->assertNotFalse($zip->locateName('xl/media/image1.png'));
        $this->assertNotFalse($zip->locateName('xl/media/image2.png'));
        $this->assertNotFalse($zip->locateName('xl/drawings/drawing1.xml'));
        $this->assertStringContainsString('<drawing r:id="rId1"/>', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('drawing+xml', $zip->getFromName('[Content_Types].xml'));
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
                $this->assertNotFalse(@simplexml_load_string($zip->getFromIndex($i)), 'well-formed: '.$name);
            }
        }
        $zip->close();
        @unlink($file);

        // PDF: the logos are embedded as images (needs PHP's GD extension for PNG).
        if (extension_loaded('gd')) {
            $pdf = $this->get(route('admin.rounds.export.pdf', $round))->assertOk()->getContent();
            $this->assertStringContainsString('/Subtype /Image', $pdf);
        }

        // Turning "logos on printed results" off removes them from exports.
        $this->put(route('admin.logos.options'), ['heading_height' => 96, 'logos_on_print' => 0]);
        $file = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($file, $this->get(route('admin.rounds.export.excel', $round))->getContent());
        $zip->open($file);
        $this->assertFalse($zip->locateName('xl/media/image1.png'));
        $zip->close();
        @unlink($file);
    }
}
