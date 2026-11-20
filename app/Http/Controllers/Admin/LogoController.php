<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Logo;
use App\Models\Setting;
use App\Support\LiveDisplay;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Logos (school, organization, LGU, sponsors...) managed in Settings → Logos.
 * They appear on the right side of the judges' screen heading (and other pages).
 */
class LogoController extends Controller
{
    /** The logo manager lives in Settings now. */
    public function index()
    {
        return redirect()->to(route('admin.settings').'#logos');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(array_keys(Logo::TYPES))],
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,gif', 'max:4096'],
            'show_on_all' => ['nullable', 'boolean'],
            'events' => ['array'],
            'events.*' => ['integer', 'exists:events,id'],
        ]);

        $file = $this->storeFile($request->file('logo'));
        try {
            $logo = Logo::create([
                'name' => $data['name'],
                'type' => $data['type'] ?? 'other',
                'file' => $file,
                'show_on_all' => $request->boolean('show_on_all'),
                'sort_order' => ((int) Logo::max('sort_order')) + 1,
            ]);
        } catch (\Throwable $e) {
            File::delete(public_path('uploads/'.$file));   // never leave an orphan image behind
            throw $e;
        }
        $logo->events()->sync($logo->show_on_all ? [] : ($data['events'] ?? []));
        ActivityLog::record('settings', 'Added logo "'.$logo->name.'" ('.$logo->typeLabel().').');
        LiveDisplay::touch();

        return redirect()->to(url()->previous().'#logos')->with('success', 'Logo "'.$logo->name.'" added. Judges\' screens update by themselves.');
    }

    public function update(Request $request, Logo $logo)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(array_keys(Logo::TYPES))],
            'sort_order' => ['required', 'integer', 'min:1', 'max:999'],
            'scale' => ['nullable', 'integer', 'min:40', 'max:250'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,gif', 'max:4096'],
            'events' => ['array'],
            'events.*' => ['integer', 'exists:events,id'],
        ]);

        $changes = [
            'name' => $data['name'],
            'type' => $data['type'] ?? $logo->type,
            'sort_order' => $data['sort_order'],
            'scale' => $data['scale'] ?? $logo->scale,
            'show_on_all' => $request->boolean('show_on_all'),
        ];
        if ($request->hasFile('logo')) {   // replace the image
            $old = $logo->file;
            $changes['file'] = $this->storeFile($request->file('logo'));
            File::delete(public_path('uploads/'.$old));
        }
        $logo->update($changes);
        $logo->events()->sync($logo->show_on_all ? [] : ($data['events'] ?? []));
        ActivityLog::record('settings', 'Updated logo "'.$logo->name.'".');
        LiveDisplay::touch();

        return redirect()->to(url()->previous().'#logo-'.$logo->id)->with('success', 'Logo "'.$logo->name.'" saved.');
    }

    public function destroy(Logo $logo)
    {
        $logo->delete();
        ActivityLog::record('settings', 'Deleted logo "'.$logo->name.'".');
        LiveDisplay::touch();

        return redirect()->to(url()->previous().'#logos')->with('success', 'Logo deleted.');
    }

    /** Size, and where else logos appear (printed results, login page). */
    public function options(Request $request)
    {
        $data = $request->validate(['heading_height' => ['required', 'integer', 'min:40', 'max:200']]);
        Setting::put([
            'logos.heading_height' => $data['heading_height'],
            'logos.on_print' => $request->boolean('logos_on_print'),
            'logos.on_login' => $request->boolean('logos_on_login'),
        ]);
        LiveDisplay::touch();

        return redirect()->to(url()->previous().'#logos')->with('success', 'Logo options saved.');
    }

    protected function storeFile(UploadedFile $file): string
    {
        File::ensureDirectoryExists(public_path('uploads/logos'));
        $name = Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $file->move(public_path('uploads/logos'), $name);
        \App\Support\ImageOptimizer::shrink(public_path('uploads/logos/'.$name), 800);   // keep exports and pages light

        return 'logos/'.$name;
    }
}
