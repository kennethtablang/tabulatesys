<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Round;
use App\Models\Score;
use App\Models\Setting;
use App\Services\TabulationService;
use App\Support\XlsxWriter;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Download a round's results as an Excel workbook or a PDF. */
class ExportController extends Controller
{
    public const CREDIT = 'Team Tabulators of STI Alaminos';   // = config('app.provider')

    public function excel(Round $round, TabulationService $tabulation)
    {
        $round->load('event', 'categories.criteria');
        $event = $round->event;
        $this->authorizeEvent($event);

        $results = $tabulation->roundResults($round);
        $ranking = $event->scoring_method === 'ranking';
        $carry = $results['carry'];
        $judges = $results['judges'];
        $xlsx = new XlsxWriter;
        $logoFiles = $this->logoFiles($event);
        // Logos sit on the right of each sheet's title rows (rows 1-5 are about 100 px tall).
        $placeLogos = function (int $titleWidthPx) use ($xlsx, $logoFiles) {
            $x = max(420, $titleWidthPx);
            foreach ($logoFiles as $path) {
                $x += $xlsx->addImage($path, $x, 6, 64) + 12;
            }
        };

        $head = fn (string $title) => [
            [['v' => $event->name, 's' => 'title']],
            [['v' => $title, 's' => 'subtitle']],
            [['v' => Event::METHODS[$event->scoring_method].($carry ? ' · Final = '.num(100 - $carry['percent']).'% this round + '.num($carry['percent']).'% '.$carry['from']->name : ''), 's' => 'muted']],
            [['v' => trim(($event->venue ?? '').($event->event_date ? ' · '.$event->event_date->format('F d, Y') : ''), ' ·').' · Generated '.now()->format('F d, Y h:i A'), 's' => 'muted']],
            [],
        ];
        $foot = [[], [['v' => config('app.name').' · '.config('app.full_name').' · © '.date('Y').' '.self::CREDIT, 's' => 'muted']]];
        $h = fn (array $labels) => array_map(fn ($l) => ['v' => $l, 's' => 'header'], $labels);
        $n = fn ($v) => $v === null ? null : round((float) $v, 4);

        // ---- Overall standing, one sheet per division ----
        foreach ($results['divisions'] as $division => $data) {
            $labels = ['Rank', 'No.', 'Contestant', 'Representing'];
            if ($ranking) {
                foreach ($judges as $j) {
                    array_push($labels, $j->name, $j->name.' rank');
                }
                if ($carry) {
                    $labels[] = $carry['from']->name.' avg';
                }
                array_push($labels, 'Rank sum', 'Average');
            } else {
                foreach ($results['categories'] as $c) {
                    $labels[] = $c->name.' ('.num($c->weight).'%)';
                }
                if ($carry) {
                    array_push($labels, 'This round', $carry['from']->name);
                }
                $labels[] = 'Total';
            }

            $rows = array_merge($head(($division ? $division.' · ' : '').$round->name.' · Overall standing'), [$h($labels)]);
            foreach ($data['overall'] as $row) {
                $c = $row['contestant'];
                $line = [['v' => $row['rank'], 's' => 'bold'], $c->number, $c->name, $c->representing];
                if ($ranking) {
                    foreach ($judges as $j) {
                        array_push($line, $n($row['judge_scores'][$j->id] ?? null), $n($row['judge_ranks'][$j->id] ?? null));
                    }
                    if ($carry) {
                        $line[] = $n($row['carried']);
                    }
                    array_push($line, ['v' => $n($row['rank_sum']), 's' => 'number-bold'], $n($row['average']));
                } else {
                    foreach ($results['categories'] as $cat) {
                        $line[] = $n($row['categories'][$cat->id]['average'] ?? null);
                    }
                    if ($carry) {
                        array_push($line, $n($row['round_score']), $n($row['carried']));
                    }
                    $line[] = ['v' => $n($row['average']), 's' => 'number-bold'];
                }
                $rows[] = $line;
            }
            $widths = array_merge([7, 6, 28, 26], array_fill(0, count($labels) - 4, 14));
            $xlsx->addSheet(($division ? $division.' ' : '').'Overall', array_merge($rows, $foot), $widths, 6);
            $placeLogos(520);
        }

        // ---- Each category with every judge's score ----
        foreach ($results['categories'] as $cat) {
            $labels = array_merge(['Rank', 'No.', 'Contestant', 'Division'], $judges->pluck('name')->all(), ['Average'], $ranking ? ['Rank sum'] : []);
            $rows = array_merge($head($round->name.' · '.$cat->name.' ('.num($cat->weight).'% of round)'), [$h($labels)]);
            foreach ($results['divisions'] as $division => $data) {
                foreach ($data['categories'][$cat->id] as $row) {
                    $c = $row['contestant'];
                    $line = [['v' => $row['rank'], 's' => 'bold'], $c->number, $c->name, $c->division];
                    foreach ($judges as $j) {
                        $line[] = $n($row['judge_scores'][$j->id] ?? null);
                    }
                    $line[] = ['v' => $n($row['average']), 's' => 'number-bold'];
                    if ($ranking) {
                        $line[] = $n($row['rank_sum']);
                    }
                    $rows[] = $line;
                }
            }
            $xlsx->addSheet($cat->name, array_merge($rows, $foot), array_merge([7, 6, 28, 10], array_fill(0, count($labels) - 4, 14)), 6);
            $placeLogos(420);
        }

        // ---- Raw criterion scores (for auditing) ----
        $criteria = $round->categories->flatMap->criteria->keyBy('id');
        $scores = Score::with(['contestant:id,number,name,division', 'judge:id,name'])
            ->whereIn('category_id', $round->categories->pluck('id'))
            ->get()
            ->sortBy(fn ($s) => sprintf('%05d|%05d|%s|%06d', $criteria[$s->criterion_id]?->category_id, $s->criterion_id, $s->judge?->name, $s->contestant?->number));
        $rows = array_merge($head($round->name.' · Raw criterion scores'), [$h(['Category', 'Criterion', 'Max', 'No.', 'Contestant', 'Division', 'Judge', 'Score'])]);
        $catNames = $round->categories->pluck('name', 'id');
        foreach ($scores as $s) {
            $crit = $criteria[$s->criterion_id] ?? null;
            $rows[] = [$catNames[$s->category_id] ?? '', $crit?->name, $crit ? (float) $crit->max_score : null,
                $s->contestant?->number, $s->contestant?->name, $s->contestant?->division, $s->judge?->name, (float) $s->score];
        }
        $xlsx->addSheet('Raw scores', array_merge($rows, $foot), [18, 22, 7, 6, 26, 10, 22, 9], 6);
        $placeLogos(520);

        ActivityLog::record('event', 'Exported '.$round->name.' results to Excel.', $event);

        return response($xlsx->toString(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($round, 'xlsx').'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function pdf(Request $request, Round $round, TabulationService $tabulation)
    {
        $round->load('event');
        $event = $round->event;
        $this->authorizeEvent($event);

        $results = $tabulation->roundResults($round);
        $detailed = $request->boolean('detailed');

        $logos = collect($this->logoFiles($event));

        $html = view('admin.results.pdf', [
            'event' => $event, 'round' => $round, 'results' => $results, 'detailed' => $detailed,
            'logos' => $logos, 'credit' => self::CREDIT, 'creditLogo' => public_path('images/sti-alaminos.jpg'),
            'organization' => Setting::get('general.organization'), 'tabulator' => $request->user()->name,
        ])->render();

        $options = new Options;
        $options->setChroot(public_path());
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        ActivityLog::record('event', 'Exported '.$round->name.' results to PDF'.($detailed ? ' (detailed)' : '').'.', $event);

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('inline') ? 'inline' : 'attachment').'; filename="'.$this->filename($round, 'pdf', $detailed ? 'detailed' : 'summary').'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Logo files (school, organization, LGU...) for this event's exports, in display order.
     * PNG/GIF need PHP's GD extension inside the PDF engine; without it only JPEG logos are used.
     */
    protected function logoFiles(Event $event): array
    {
        if (Setting::get('logos.on_print', '1') !== '1') {
            return [];
        }
        $gd = extension_loaded('gd');

        return $event->displayLogos()
            ->map(fn ($l) => public_path('uploads/'.$l->file))
            ->filter(fn ($path) => is_file($path) && ($gd || preg_match('/\.jpe?g$/i', $path)))
            ->values()->all();
    }

    protected function filename(Round $round, string $ext, string $suffix = 'results'): string
    {
        return Str::slug($round->event->name).'-'.Str::slug($round->name).'-'.$suffix.'-'.now()->format('Ymd-Hi').'.'.$ext;
    }
}
