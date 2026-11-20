<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Setting;
use App\Support\JudgeDisplay;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index()
    {
        $display = JudgeDisplay::defaults();
        $general = [
            'app_name' => Setting::get('general.app_name', config('app.name')),
            'organization' => Setting::get('general.organization', ''),
            'logo_size' => Setting::get('logos.size', 'medium'),
            'heading_height' => \App\Models\Logo::headingHeight(),
            'logos_on_print' => Setting::get('logos.on_print', '1') === '1',
            'logos_on_login' => Setting::get('logos.on_login', '1') === '1',
            'logo_count' => \App\Models\Logo::count(),
            'accent' => \App\Support\Theme::accent(),
        ];
        $previewCategories = $this->previewCategories();
        $logos = \App\Models\Logo::ordered()->get();
        $events = \App\Models\Event::with('logos:id')->orderBy('name')->get(['id', 'name']);

        return view('admin.settings.index', compact('display', 'general', 'previewCategories', 'logos', 'events'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:80'],
            'organization' => ['nullable', 'string', 'max:120'],
            'sheet_layout' => ['required', Rule::in(array_keys(JudgeDisplay::LAYOUTS))],
            'photo_size' => ['required', Rule::in(array_keys(JudgeDisplay::PHOTO_SIZES))],
            'contestant_order' => ['required', Rule::in(array_keys(JudgeDisplay::ORDERS))],
            'input_size' => ['required', Rule::in(array_keys(JudgeDisplay::INPUT_SIZES))],
            'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], ['accent.regex' => 'Pick a highlight colour like #1ab394.']);

        $values = [
            'general.app_name' => $data['app_name'],
            'general.organization' => $data['organization'] ?? '',
            'judge.sheet_layout' => $data['sheet_layout'],
            'judge.photo_size' => $data['photo_size'],
            'judge.contestant_order' => $data['contestant_order'],
            'judge.input_size' => $data['input_size'],
            'theme.accent' => strtolower($data['accent']),
        ];
        foreach (['allow_switch', 'show_photos', 'show_names', 'show_details', 'show_totals', 'show_my_rank'] as $flag) {
            $values['judge.'.$flag] = $request->boolean($flag);
        }

        Setting::put($values);
        \App\Support\LiveDisplay::touch();
        ActivityLog::record('settings', 'Updated settings (judge layout: '.JudgeDisplay::LAYOUTS[$data['sheet_layout']][0].').');

        return redirect()->route('admin.settings')->with('success', 'Settings saved. Judges\' screens update by themselves within a few seconds.');
    }

    /** Show the admin exactly what a judge would see, read-only. */
    public function preview(Request $request)
    {
        $category = Category::with(['criteria', 'round.contestants', 'event'])->find($request->integer('category'))
            ?? $this->previewCategories()->first();

        if (! $category) {
            return redirect()->route('admin.settings')->with('error', 'Add an event with a category, criteria and contestants first to preview the judge view.');
        }
        $category->loadMissing(['criteria', 'round.contestants', 'event']);

        $display = JudgeDisplay::forEvent($category->event, null);
        if (isset(JudgeDisplay::LAYOUTS[$request->query('layout', '')])) {
            $display['layout'] = $request->query('layout');
            if ($display['layout'] === 'compact') {
                $display['show_photos'] = false;
            }
        }

        return view('judge.sheet', [
            'category' => $category,
            'scores' => collect(),
            'submission' => null,
            'editable' => false,
            'display' => $display,
            'contestants' => JudgeDisplay::sort($category->round->contestants, $display['contestant_order']),
            'preview' => true,
        ]);
    }

    protected function previewCategories()
    {
        return Category::with(['event:id,name,sheet_layout', 'round'])
            ->whereHas('criteria')->whereHas('round.contestants')
            ->orderByDesc('id')->get();
    }
}
