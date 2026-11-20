<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\Setting;
use App\Models\User;
use App\Support\LiveDisplay;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ExportTest extends TestCase
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

    public function test_results_export_to_a_valid_excel_workbook(): void
    {
        $prelim = Event::where('name', 'Mr. & Ms. Foundation Day 2026')->firstOrFail()->rounds()->first();

        $response = $this->actingAs($this->admin())->get(route('admin.rounds.export.excel', $prelim));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        $file = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($file, $response->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($file) === true, 'xlsx is a zip');

        $workbook = $zip->getFromName('xl/workbook.xml');
        foreach (['Mr Overall', 'Ms Overall', 'Production Number', 'Sports Wear', 'Talent', 'Formal Wear', 'Raw scores'] as $sheet) {
            $this->assertStringContainsString('name="'.$sheet.'"', $workbook);
        }
        // Every XML part is well-formed.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $xml = $zip->getFromIndex($i);
            $this->assertNotFalse(@simplexml_load_string($xml), 'well-formed: '.$zip->getNameIndex($i));
        }
        $overall = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Enzo Villanueva', $overall);
        $this->assertStringContainsString('Team Tabulators of STI Alaminos', $overall);
        $zip->close();
        @unlink($file);
    }

    public function test_results_export_to_pdf_summary_and_detailed(): void
    {
        $round = Event::where('name', 'Battle of the Voices 2026')->firstOrFail()->rounds()->first();
        $this->actingAs($this->admin());

        $pdf = $this->get(route('admin.rounds.export.pdf', $round));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $detailed = $this->get(route('admin.rounds.export.pdf', [$round, 'detailed' => 1]));
        $this->assertStringStartsWith('%PDF', $detailed->getContent());
        $this->assertStringContainsString('detailed', $detailed->headers->get('Content-Disposition'));

        $this->get(route('admin.events.results', $round->event))->assertSee('Excel')->assertSee('PDF · detailed');
    }

    public function test_tabulator_can_only_export_their_own_event(): void
    {
        $own = Event::where('name', 'Battle of the Voices 2026')->firstOrFail();
        $other = Event::where('name', 'Mr. & Ms. Foundation Day 2026')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.events.tabulators.store', $own));
        $tab = User::where('event_id', $own->id)->firstOrFail();

        $this->actingAs($tab)->get(route('admin.rounds.export.excel', $own->rounds()->first()))->assertOk();
        $this->actingAs($tab)->get(route('admin.rounds.export.excel', $other->rounds()->first()))->assertForbidden();
        $this->actingAs($tab)->get(route('admin.rounds.export.pdf', $other->rounds()->first()))->assertForbidden();
        $this->actingAs(User::where('username', 'judge1')->first())->get(route('admin.rounds.export.pdf', $own->rounds()->first()))->assertForbidden();
    }

    public function test_unlocking_a_sheet_reloads_only_that_judges_screen(): void
    {
        $category = Category::where('name', 'Production Number')->firstOrFail();   // submitted in the seed
        $event = $category->event;
        [$j1, $j2] = $event->judges()->take(2)->get()->all();

        $token = fn (User $j) => LiveDisplay::token([$event->id], $j);
        $before1 = $token($j1);
        $before2 = $token($j2);

        $this->actingAs($this->admin())->delete(route('admin.categories.unlock', [$category, $j1]))->assertRedirect();
        $this->assertNotSame($before1, $token($j1), 'unlocked judge reloads');
        $this->assertSame($before2, $token($j2), 'other judges are not disturbed');

        // Opening/locking a category reloads that event's judges.
        $this->patch(route('admin.categories.status', $category), ['status' => 'open']);
        $this->assertNotSame($before2, $token($j2));
    }

    public function test_copyright_and_provider_logo_are_shown(): void
    {
        $this->get('/login')->assertSee('Team Tabulators of STI Alaminos')->assertSee('images/sti-alaminos.png', false);
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertSee('Team Tabulators of STI Alaminos');
        $this->get(route('admin.rounds.print', Event::first()->rounds()->first()))->assertSee('Team Tabulators of STI Alaminos');
        $this->assertFileExists(public_path('images/sti-alaminos.jpg'));
    }

    public function test_judge_sheet_has_no_repeated_criteria_boxes(): void
    {
        $talent = Category::where('name', 'Talent')->firstOrFail();
        $html = $this->actingAs(User::where('username', 'judge1')->first())->get(route('judge.sheet', $talent))->assertOk()->getContent();
        $this->assertStringNotContainsString('class="border p-xs"', $html);
        $this->assertSame(1, substr_count($html, '>Mastery<small>'), 'criterion appears once, in the table header');
    }
}
